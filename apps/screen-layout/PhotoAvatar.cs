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

// Approved single-face sample. The resting face and all motion share one transform.
internal sealed class PhotoAvatar : IDisposable {
 readonly Bitmap portrait; readonly Bitmap[] mouths=new Bitmap[6],eyes=new Bitmap[4];
 readonly FaceMotion motion; readonly NarrationAudio voice; readonly Stopwatch clock=Stopwatch.StartNew();
 double lastFrame;
 internal readonly Rectangle MouthRegion=new Rectangle(391,131,58,44),EyeRegion=new Rectangle(373,87,90,29);
 const double Scale=.9, MouthX=420, MouthY=153;
 internal PhotoAvatar(int person,int color,NarrationAudio audio){
  if(person!=0||color!=0)throw new ArgumentOutOfRangeException("person","Bản mẫu dùng một khuôn mặt tóc dài, áo đen đã duyệt.");
  voice=audio;
  string path=Path.Combine(AppDomain.CurrentDomain.BaseDirectory,"presenter-"+(person*4+color+1).ToString("00")+".png");
  if(!File.Exists(path))throw new FileNotFoundException("Thiếu ảnh nhân vật: "+Path.GetFileName(path)+". Giải nén toàn bộ gói ứng dụng.",path);
  portrait=new Bitmap(836,470,PixelFormat.Format24bppRgb);
  try{
   using(var image=new Bitmap(path))using(var g=Graphics.FromImage(portrait))g.DrawImage(image,new Rectangle(0,0,836,470));
   motion=new FaceMotion(audio);
   using(var rest=motion.Render(0,0)){
    RegisterFace(rest);
    mouths[0]=(Bitmap)portrait.Clone();eyes[0]=(Bitmap)portrait.Clone();
    for(int i=1;i<6;i++)using(var frame=motion.Render(i/5f,0))mouths[i]=MotionFrame(rest,frame,MouthRegion);
    for(int i=1;i<4;i++)using(var frame=motion.Render(0,i/3f))eyes[i]=MotionFrame(rest,frame,EyeRegion);
   }
  }catch{Dispose();throw;}
 }
 static double SourceX(int x){return 136+(x-MouthX)/Scale;}
 static double SourceY(int y){return 160+(y-MouthY)/Scale;}
 void RegisterFace(Bitmap face){
  // Register the whole neutral face once, including the resting mouth. This avoids
  // switching between two different lip contours when speech begins or ends.
  double dr=0,dg=0,db=0;
  for(int k=0;k<32;k++){
   double a=k*Math.PI/16,sx=134+58*.92*Math.Cos(a),sy=126+80*.92*Math.Sin(a);
   Color from=FaceMotion.Sample(face,sx,sy),to=FaceMotion.Sample(portrait,MouthX+(sx-136)*Scale,MouthY+(sy-160)*Scale);
   dr+=to.R-from.R;dg+=to.G-from.G;db+=to.B-from.B;
  }
  dr/=32;dg/=32;db/=32;
  for(int y=48;y<196;y++)for(int x=364;x<475;x++){
   double sx=SourceX(x),sy=SourceY(y),u=(sx-134)/58,v=(sy-126)/80,q=Math.Sqrt(u*u+v*v);
   if(q>=1)continue;double a=Math.Min(1,(1-q)/.24);a=a*a*(3-2*a);
   Color c=FaceMotion.Sample(face,sx,sy);c=Color.FromArgb(Clamp(c.R+dr),Clamp(c.G+dg),Clamp(c.B+db));
   portrait.SetPixel(x,y,FaceMotion.Mix(portrait.GetPixel(x,y),c,a));
  }
 }
 Bitmap MotionFrame(Bitmap rest,Bitmap frame,Rectangle region){
  var result=(Bitmap)portrait.Clone();
  // Transfer the approved sprite differences, not a newly stretched mouth.
  // One fixed base/color correction keeps the skin and rest position continuous.
  for(int y=region.Top;y<region.Bottom;y++)for(int x=region.Left;x<region.Right;x++){
   Color a=FaceMotion.Sample(rest,SourceX(x),SourceY(y)),b=FaceMotion.Sample(frame,SourceX(x),SourceY(y)),p=portrait.GetPixel(x,y);
   result.SetPixel(x,y,Color.FromArgb(Clamp(p.R+b.R-a.R),Clamp(p.G+b.G-a.G),Clamp(p.B+b.B-a.B)));
  }
  return result;
 }
 static int Clamp(double n){return Math.Max(0,Math.Min(255,(int)Math.Round(n)));}
 internal static float Blink(double seconds){return FaceMotion.Blink(seconds);}
 internal float AdvanceMouth(float level,double dt){return motion.AdvanceMouth(level,dt);}
 internal Bitmap Take(){double now=clock.Elapsed.TotalSeconds,dt=Math.Min(.1,Math.Max(0,now-lastFrame));lastFrame=now;return Render(AdvanceMouth(voice==null?0:voice.Level,dt),Blink(now));}
 internal Bitmap Render(float amplitude,float blink){var result=(Bitmap)portrait.Clone();FaceMotion.BlendTrack(result,mouths,Math.Max(0,Math.Min(1,amplitude))*5,MouthRegion);FaceMotion.BlendTrack(result,eyes,Math.Max(0,Math.Min(1,blink))*3,EyeRegion);return result;}
 public void Dispose(){if(portrait!=null)portrait.Dispose();if(motion!=null)motion.Dispose();foreach(var b in mouths)if(b!=null)b.Dispose();foreach(var b in eyes)if(b!=null)b.Dispose();}
}
