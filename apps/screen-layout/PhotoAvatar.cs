using System;
using System.Drawing;
using System.Drawing.Imaging;
using System.Diagnostics;
using System.IO;

// One registered face. Audio amplitude drives opening, not phoneme recognition.
internal sealed class FaceMotion : IDisposable {
 readonly Bitmap portrait; readonly Bitmap[] mouths=new Bitmap[6],eyes=new Bitmap[4];
 readonly NarrationAudio voice; readonly Stopwatch clock=Stopwatch.StartNew();
 double lastFrame; float opening;
 internal FaceMotion(NarrationAudio audio){
  voice=audio;
  string path=Path.Combine(AppDomain.CurrentDomain.BaseDirectory,"face-transitions.png");
  if(!File.Exists(path))throw new FileNotFoundException("Thiếu bộ ảnh chuyển động face-transitions.png",path);
  using(var atlas=new Bitmap(path)){
   // The generated sheet has uneven cells: do not divide its width by six.
   double sx=atlas.Width/1536.0,sy=atlas.Height/1024.0;
   portrait=new Bitmap(256,256,PixelFormat.Format24bppRgb);
   using(var g=Graphics.FromImage(portrait))g.DrawImage(atlas,new Rectangle(0,0,256,256),0,0,(float)(256*sx),(float)(256*sy),GraphicsUnit.Pixel);
   double[] centers={136,412,680,948,1216,1474};
   mouths[0]=(Bitmap)portrait.Clone();eyes[0]=(Bitmap)portrait.Clone();
   for(int i=1;i<6;i++)mouths[i]=Patch(atlas,centers[i],160,136,160,30,23,sx,sy);
   // Register each eyelid separately. Brows, bridge of nose and cheeks stay in the base.
   double[,] left={{1187,875},{382,878},{919,881}};
   double[,] right={{1240,870},{435,872},{970,876}};
   for(int i=1;i<4;i++){
    eyes[i]=(Bitmap)portrait.Clone();
    PaintPatch(eyes[i],atlas,left[i-1,0],left[i-1,1],107,104,22,12,sx,sy);
    PaintPatch(eyes[i],atlas,right[i-1,0],right[i-1,1],158,100,22,12,sx,sy);
   }
  }
 }
 // Precompute soft, registered patches once. No skin recoloring or painted teeth.
 Bitmap Patch(Bitmap atlas,double sourceX,double sourceY,int cx,int cy,int rx,int ry,double sx,double sy){
  var result=(Bitmap)portrait.Clone();
  PaintPatch(result,atlas,sourceX,sourceY,cx,cy,rx,ry,sx,sy);
  return result;
 }
 void PaintPatch(Bitmap result,Bitmap atlas,double sourceX,double sourceY,int cx,int cy,int rx,int ry,double sx,double sy){
  for(int y=cy-ry;y<=cy+ry;y++)for(int x=cx-rx;x<=cx+rx;x++){
   double u=(x-cx)/(double)rx,v=(y-cy)/(double)ry,q=Math.Sqrt(u*u+v*v);
   if(q>=1)continue;
   double alpha=Math.Min(1,(1-q)/.35);alpha=alpha*alpha*(3-2*alpha);
   Color source=Sample(atlas,(sourceX+x-cx)*sx,(sourceY+y-cy)*sy);
   result.SetPixel(x,y,Mix(portrait.GetPixel(x,y),source,alpha));
  }
 }
 internal static Color Sample(Bitmap b,double x,double y){
  x=Math.Max(0,Math.Min(b.Width-1,x));y=Math.Max(0,Math.Min(b.Height-1,y));
  int ix=(int)x,iy=(int)y;
  return Mix(Mix(b.GetPixel(ix,iy),b.GetPixel(Math.Min(ix+1,b.Width-1),iy),x-ix),Mix(b.GetPixel(ix,Math.Min(iy+1,b.Height-1)),b.GetPixel(Math.Min(ix+1,b.Width-1),Math.Min(iy+1,b.Height-1)),x-ix),y-iy);
 }
 internal static float Blink(double seconds){
  double t=seconds%4.6-4.04;
  if(t<=0)return 0;
  if(t<.18)return (float)Ease(t/.18);
  if(t<.22)return 1;
  return (float)(1-Ease((t-.22)/.34));
 }
 static double Ease(double t){t=Math.Max(0,Math.Min(1,t));return t*t*(3-2*t);}
 internal Bitmap Take(){
  double now=clock.Elapsed.TotalSeconds,dt=Math.Min(.1,Math.Max(0,now-lastFrame));lastFrame=now;
  AdvanceMouth(voice==null?0:voice.Level,dt);
  return Render(opening,Blink(now));
 }
 // Keep natural loudness dips rather than holding a wide-open mouth between syllables.
 // The small noise gate also prevents breathing/background noise from moving the lips.
 internal float AdvanceMouth(float level,double dt){
  level=Math.Max(0,Math.Min(1,level));
  float target=level<=.035f?0:.72f*(float)Math.Pow((level-.035f)/.965f,.85);
  dt=Math.Max(0,Math.Min(.25,dt));
  opening+=(target-opening)*(float)(1-Math.Exp(-dt/(target>opening?.045:.050)));
  if(opening<.008f)opening=0;
  return opening;
 }
 internal Bitmap Render(float amplitude,float blink){
  var result=(Bitmap)portrait.Clone();
  BlendTrack(result,mouths,Math.Max(0,Math.Min(1,amplitude))*5,new Rectangle(106,137,61,47));
  BlendTrack(result,eyes,Math.Max(0,Math.Min(1,blink))*3,new Rectangle(85,88,96,29));
  return result;
 }
 internal static void BlendTrack(Bitmap dst,Bitmap[] track,double phase,Rectangle region){
  if(phase<=0)return;
  int first=Math.Min(track.Length-1,(int)phase),next=Math.Min(track.Length-1,first+1);
  double t=phase-first;t=t*t*(3-2*t);
  for(int y=region.Top;y<region.Bottom;y++)for(int x=region.Left;x<region.Right;x++)dst.SetPixel(x,y,Mix(track[first].GetPixel(x,y),track[next].GetPixel(x,y),t));
 }
 internal static Color Mix(Color a,Color b,double t){return Color.FromArgb(Channel(a.R+(b.R-a.R)*t),Channel(a.G+(b.G-a.G)*t),Channel(a.B+(b.B-a.B)*t));}
 static int Channel(double n){return Math.Max(0,Math.Min(255,(int)Math.Round(n)));}
 public void Dispose(){portrait.Dispose();foreach(var b in mouths)if(b!=null)b.Dispose();foreach(var b in eyes)if(b!=null)b.Dispose();}
}

// Preserve each portrait's forehead, hair, brows, nose, jaw and neck.
// Register only the lips and individual eyelids against measured per-look landmarks.
internal sealed class PhotoAvatar : IDisposable {
 readonly Bitmap original,portrait; readonly Bitmap[] mouths=new Bitmap[6],eyes=new Bitmap[4];
 readonly FaceMotion motion; readonly NarrationAudio voice; readonly Stopwatch clock=Stopwatch.StartNew();
 readonly Feature[] features;
 double lastFrame;
 internal readonly Rectangle MouthRegion,EyeRegion;
 sealed class Feature {
  internal double SX,SY,X,Y,Scale,RX,RY;
  internal Rectangle Bounds {get{return Rectangle.FromLTRB((int)Math.Floor(X-RX*Scale),(int)Math.Floor(Y-RY*Scale),(int)Math.Ceiling(X+RX*Scale),(int)Math.Ceiling(Y+RY*Scale));}}
  internal double SourceX(int x){return SX+(x-X)/Scale;}
  internal double SourceY(int y){return SY+(y-Y)/Scale;}
  internal double Alpha(int x,int y){double u=(x-X)/(RX*Scale),v=(y-Y)/(RY*Scale),q=Math.Sqrt(u*u+v*v);double a=Math.Max(0,Math.Min(1,(1-q)/.35));return a*a*(3-2*a);}
 }
 internal PhotoAvatar(int person,int color,NarrationAudio audio){
  if(person<0||person>3||color<0||color>3)throw new ArgumentOutOfRangeException("person","Chọn bối cảnh và mẫu trong danh sách.");
  voice=audio;string path=Path.Combine(AppDomain.CurrentDomain.BaseDirectory,"studio-looks-16.png");
  if(!File.Exists(path))throw new FileNotFoundException("Thiếu studio-looks-16.png. Giải nén toàn bộ gói ứng dụng.",path);
  double[,] landmarks={{243.77,79.13,231.71,55.44,254.23,53.81},
   {727.33,78.35,715.18,55.24,737.39,53.53},
   {1211.18,78.46,1198.9,55.1,1221.19,53.44},
   {1695.4,78.45,1682.73,55.15,1705.15,53.45},
   {245.91,288.78,233.35,265.77,255.9,263.87},
   {728.89,289.25,716.67,265.8,738.78,263.86},
   {1212.61,288.64,1200.59,265.68,1222.76,263.89},
   {1695.81,290.1,1683.91,266.05,1706.01,264.17},
   {245.56,498.44,233.38,475.52,255.52,473.79},
   {728.98,498.59,716.63,475.63,738.61,473.88},
   {1212.8,498.3,1200.37,475.49,1222.83,473.75},
   {1696.73,498.42,1684.57,475.33,1706.73,473.7},
   {246.03,701.34,232.92,678.67,255.63,676.94},
   {728.98,701.35,716.28,678.47,738.96,676.83},
   {1213.1,701.05,1200.24,678.43,1222.94,676.56},
   {1696.56,701.57,1684.3,678.72,1706.86,677.07}};
  int index=person*4+color;double mx=landmarks[index,0],my=landmarks[index,1],zoom=470.0/180;
  double lx=landmarks[index,2],ly=landmarks[index,3],rx=landmarks[index,4],ry=landmarks[index,5];
  double scale=(rx-lx)*zoom/(157.985-107.238);
  features=new[]{new Feature{SX=136.789,SY=156.684,X=420,Y=190,Scale=scale,RX=28,RY=22},
   new Feature{SX=107.238,SY=103.372,X=420+(lx-mx)*zoom,Y=190+(ly-my)*zoom,Scale=scale,RX=22,RY=11},
   new Feature{SX=157.985,SY=99.205,X=420+(rx-mx)*zoom,Y=190+(ry-my)*zoom,Scale=scale,RX=22,RY=11}};
  MouthRegion=features[0].Bounds;EyeRegion=Rectangle.Union(features[1].Bounds,features[2].Bounds);
  original=new Bitmap(836,470,PixelFormat.Format24bppRgb);
  try{
   using(var image=new Bitmap(path))using(var g=Graphics.FromImage(original)){
    double sx=image.Width/1933.0,sy=image.Height/813.0;
    var crop=new RectangleF((float)((mx-420/zoom)*sx),(float)((my-190/zoom)*sy),(float)(836/zoom*sx),(float)(180*sy));
    if(crop.Left<0||crop.Top<0||crop.Right>image.Width||crop.Bottom>image.Height)throw new InvalidDataException("Bộ ảnh 16 mẫu không đúng kích thước.");
    g.InterpolationMode=System.Drawing.Drawing2D.InterpolationMode.HighQualityBicubic;
    g.DrawImage(image,new RectangleF(0,0,836,470),crop,GraphicsUnit.Pixel);
   }
   portrait=(Bitmap)original.Clone();motion=new FaceMotion(audio);
   using(var rest=motion.Render(0,0)){
    foreach(var f in features)RegisterFeature(rest,f);
    mouths[0]=(Bitmap)portrait.Clone();eyes[0]=(Bitmap)portrait.Clone();
    for(int i=1;i<6;i++)using(var frame=motion.Render(i/5f,0))mouths[i]=MotionFrame(rest,frame,new[]{features[0]});
    for(int i=1;i<4;i++)using(var frame=motion.Render(0,i/3f))eyes[i]=MotionFrame(rest,frame,new[]{features[1],features[2]});
   }
  }catch{Dispose();throw;}
 }
 void RegisterFeature(Bitmap rest,Feature f){
  double dr=0,dg=0,db=0;
  for(int k=0;k<32;k++){
   double a=k*Math.PI/16,sx=f.SX+f.RX*.98*Math.Cos(a),sy=f.SY+f.RY*.98*Math.Sin(a);
   Color from=FaceMotion.Sample(rest,sx,sy),to=FaceMotion.Sample(original,f.X+(sx-f.SX)*f.Scale,f.Y+(sy-f.SY)*f.Scale);
   dr+=to.R-from.R;dg+=to.G-from.G;db+=to.B-from.B;
  }
  dr=Math.Max(-30,Math.Min(30,dr/32));dg=Math.Max(-30,Math.Min(30,dg/32));db=Math.Max(-30,Math.Min(30,db/32));
  Rectangle r=f.Bounds;
  for(int y=r.Top;y<r.Bottom;y++)for(int x=r.Left;x<r.Right;x++){
   double alpha=f.Alpha(x,y);if(alpha<=0)continue;
   Color c=FaceMotion.Sample(rest,f.SourceX(x),f.SourceY(y));c=Color.FromArgb(Clamp(c.R+dr),Clamp(c.G+dg),Clamp(c.B+db));
   portrait.SetPixel(x,y,FaceMotion.Mix(portrait.GetPixel(x,y),c,alpha));
  }
 }
 Bitmap MotionFrame(Bitmap rest,Bitmap frame,Feature[] active){
  var result=(Bitmap)portrait.Clone();
  foreach(var f in active){Rectangle r=f.Bounds;
   for(int y=r.Top;y<r.Bottom;y++)for(int x=r.Left;x<r.Right;x++){
    double alpha=f.Alpha(x,y);if(alpha<=0)continue;double sx=f.SourceX(x),sy=f.SourceY(y);
    Color a=FaceMotion.Sample(rest,sx,sy),b=FaceMotion.Sample(frame,sx,sy),p=portrait.GetPixel(x,y);
    result.SetPixel(x,y,Color.FromArgb(Clamp(p.R+(b.R-a.R)*alpha),Clamp(p.G+(b.G-a.G)*alpha),Clamp(p.B+(b.B-a.B)*alpha)));
   }
  }
  return result;
 }
 internal Bitmap ReferencePortrait(){return (Bitmap)original.Clone();}
 static int Clamp(double n){return Math.Max(0,Math.Min(255,(int)Math.Round(n)));}
 internal static float Blink(double seconds){return FaceMotion.Blink(seconds);}
 internal float AdvanceMouth(float level,double dt){return motion.AdvanceMouth(level,dt);}
 internal Bitmap Take(){double now=clock.Elapsed.TotalSeconds,dt=Math.Min(.1,Math.Max(0,now-lastFrame));lastFrame=now;return Render(AdvanceMouth(voice==null?0:voice.Level,dt),Blink(now));}
 internal Bitmap Render(float amplitude,float blink){var result=(Bitmap)portrait.Clone();FaceMotion.BlendTrack(result,mouths,Math.Max(0,Math.Min(1,amplitude))*5,MouthRegion);FaceMotion.BlendTrack(result,eyes,Math.Max(0,Math.Min(1,blink))*3,EyeRegion);return result;}
 public void Dispose(){if(original!=null)original.Dispose();if(portrait!=null)portrait.Dispose();if(motion!=null)motion.Dispose();foreach(var b in mouths)if(b!=null)b.Dispose();foreach(var b in eyes)if(b!=null)b.Dispose();}
}
