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

// Complete studio portraits. Only registered mouth/eyelid patches are animated.
internal sealed class PhotoAvatar : IDisposable {
 readonly Bitmap portrait; readonly Bitmap[] mouths=new Bitmap[6],eyes=new Bitmap[4];
 readonly FaceMotion motion; readonly NarrationAudio voice; readonly Stopwatch clock=Stopwatch.StartNew();
 double lastFrame; internal readonly Rectangle MouthRegion,EyeRegion;
 internal PhotoAvatar(int person,int color,NarrationAudio audio){
  if(person<0||person>2||color<0||color>3)throw new ArgumentOutOfRangeException("person","Chọn nhân vật và màu áo trong danh sách.");
  string path=Path.Combine(AppDomain.CurrentDomain.BaseDirectory,"avatars.jpg");
  if(!File.Exists(path))throw new FileNotFoundException("Thiếu avatars.jpg. Giải nén toàn bộ gói ứng dụng vào cùng thư mục.",path);
  voice=audio;
  using(var sheet=new Bitmap(path)){
   int[] tops={0,288,599},heights={286,308,342};
   portrait=new Bitmap(418,heights[person],PixelFormat.Format24bppRgb);
   using(var g=Graphics.FromImage(portrait))g.DrawImage(sheet,new Rectangle(0,0,portrait.Width,portrait.Height),(float)(color*418*sheet.Width/1672.0),(float)(tops[person]*sheet.Height/941.0),(float)(418*sheet.Width/1672.0),(float)(heights[person]*sheet.Height/941.0),GraphicsUnit.Pixel);
  }
  try{
   motion=new FaceMotion(audio);
   double[,] landmarks={{250,99,229,74,259,66,-.24},{244,114,219,87,249,76,-.30},{237,124,212,94,247,86,-.24}};
   int[,] shifts={{0,-1,-1,0},{0,0,0,1},{0,0,0,0}};int dx=shifts[person,color];
   double mx=landmarks[person,0]+dx,my=landmarks[person,1],angle=landmarks[person,6];
   MouthRegion=new Rectangle((int)mx-23,(int)my-12,47,35);
   EyeRegion=new Rectangle((int)landmarks[person,2]+dx-16,(int)landmarks[person,5]-10,(int)(landmarks[person,4]-landmarks[person,2])+33, (int)(landmarks[person,3]-landmarks[person,5])+21);
   mouths[0]=(Bitmap)portrait.Clone();eyes[0]=(Bitmap)portrait.Clone();
   // Animate this portrait's own lips. Borrow only the inner mouth texture,
   // never the donor lip contour, surrounding skin, or smile corners.
   using(var interior=motion.Render(1,0))for(int i=1;i<6;i++)
    mouths[i]=OpenOwnLips(interior,mx,my,15.5,angle,i/5.0);
   for(int i=1;i<4;i++)using(var face=motion.Render(0,i/3f)){
    eyes[i]=(Bitmap)portrait.Clone();
    Transfer(eyes[i],face,107,104,landmarks[person,2]+dx,landmarks[person,3],21,11,.62,.65,angle+.08);
    Transfer(eyes[i],face,158,100,landmarks[person,4]+dx,landmarks[person,5],21,11,.62,.65,angle+.08);
   }
  }catch{Dispose();throw;}
 }
 Bitmap OpenOwnLips(Bitmap interior,double cx,double cy,double width,double angle,double amount){
  var result=(Bitmap)portrait.Clone();double co=Math.Cos(angle),si=Math.Sin(angle);
  if(amount<=0)return result;
  for(int y=MouthRegion.Top;y<MouthRegion.Bottom;y++)for(int x=MouthRegion.Left;x<MouthRegion.Right;x++){
   double red=0,green=0,blue=0;
   // Four subpixel samples avoid stair steps along a tilted smile.
   for(int oy=0;oy<2;oy++)for(int ox=0;ox<2;ox++){
    double px=x+(ox==0?-.25:.25),py=y+(oy==0?-.25:.25);
    double u=co*(px-cx)+si*(py-cy),v=-si*(px-cx)+co*(py-cy);
    if(Math.Abs(u)>=width)continue;
    double taper=Math.Pow(Math.Max(0,1-u*u/(width*width)),1.3);
    double gap=5.5*amount*taper,seam=-.6*u*u/(width*width),top=seam-gap*.22,bottom=seam+gap*.78;
    if(v<=top-7||v>=bottom+11)continue;
    bool inside=v>=top&&v<=bottom;
    double sourceV=seam;
    if(!inside){
     double distance=v<top?top-v:v-bottom,support=v<top?7:11;
     double weight=Math.Max(0,Math.Min(1,1-distance/support));weight=weight*weight*(3-2*weight);
     sourceV=v+(v<top?gap*.22:-gap*.78)*weight;
    }
    Color original=FaceMotion.Sample(portrait,px,py);
    Color warped=FaceMotion.Sample(portrait,cx+co*u-si*sourceV,cy+si*u+co*sourceV);
    if(inside&&gap>.01){
     double t=Math.Max(0,Math.Min(1,(v-top)/gap));
     Color cavity=FaceMotion.Sample(interior,136+u/width*13,154.5+t*7);
     double coverage=Math.Max(0,Math.Min(1,Math.Min((v-top)*1.5,(bottom-v)*1.5)));
     warped=FaceMotion.Mix(warped,cavity,coverage);
    }
    red+=warped.R-original.R;green+=warped.G-original.G;blue+=warped.B-original.B;
   }
   Color pixel=portrait.GetPixel(x,y);
   result.SetPixel(x,y,Color.FromArgb(Clamp(pixel.R+red/4),Clamp(pixel.G+green/4),Clamp(pixel.B+blue/4)));
  }
  return result;
 }
 void Transfer(Bitmap target,Bitmap source,double sx,double sy,double cx,double cy,double rx,double ry,double scaleX,double scaleY,double angle){
  double co=Math.Cos(angle),si=Math.Sin(angle);double dr=0,dg=0,db=0;int count=0;
  // Match surrounding skin, not the dark mouth interior, once at construction.
  for(int k=0;k<32;k++){
   double a=k*Math.PI/16,u=rx*.92*Math.Cos(a),v=ry*.92*Math.Sin(a);
   var from=FaceMotion.Sample(source,sx+u,sy+v);var to=FaceMotion.Sample(portrait,cx+co*u*scaleX-si*v*scaleY,cy+si*u*scaleX+co*v*scaleY);
   dr+=to.R-from.R;dg+=to.G-from.G;db+=to.B-from.B;count++;
  }
  dr/=count;dg/=count;db/=count;
  int radius=(int)Math.Ceiling(Math.Max(rx*scaleX,ry*scaleY))+2;
  for(int y=Math.Max(0,(int)cy-radius);y<Math.Min(target.Height,(int)cy+radius+1);y++)for(int x=Math.Max(0,(int)cx-radius);x<Math.Min(target.Width,(int)cx+radius+1);x++){
   double px=x-cx,py=y-cy,u=(co*px+si*py)/scaleX,v=(-si*px+co*py)/scaleY,q=Math.Sqrt(u*u/(rx*rx)+v*v/(ry*ry));if(q>=1)continue;
   double a=Math.Min(1,(1-q)/.3);a=a*a*(3-2*a);
   Color s=FaceMotion.Sample(source,sx+u,sy+v);
   s=Color.FromArgb(Clamp(s.R+dr),Clamp(s.G+dg),Clamp(s.B+db));
   target.SetPixel(x,y,FaceMotion.Mix(portrait.GetPixel(x,y),s,a));
  }
 }
 static int Clamp(double n){return Math.Max(0,Math.Min(255,(int)Math.Round(n)));}
 internal static float Blink(double seconds){return FaceMotion.Blink(seconds);}
 internal float AdvanceMouth(float level,double dt){return motion.AdvanceMouth(level,dt);}
 internal Bitmap Take(){double now=clock.Elapsed.TotalSeconds,dt=Math.Min(.1,Math.Max(0,now-lastFrame));lastFrame=now;return Render(AdvanceMouth(voice==null?0:voice.Level,dt),Blink(now));}
 internal Bitmap Render(float amplitude,float blink){var result=(Bitmap)portrait.Clone();FaceMotion.BlendTrack(result,mouths,Math.Max(0,Math.Min(1,amplitude))*5,MouthRegion);FaceMotion.BlendTrack(result,eyes,Math.Max(0,Math.Min(1,blink))*3,EyeRegion);return result;}
 public void Dispose(){if(portrait!=null)portrait.Dispose();if(motion!=null)motion.Dispose();foreach(var b in mouths)if(b!=null)b.Dispose();foreach(var b in eyes)if(b!=null)b.Dispose();}
}
