using System;
using System.Drawing;
using System.Drawing.Imaging;
using System.Diagnostics;
using System.IO;

// One registered face. Audio amplitude drives opening, not phoneme recognition.
internal sealed class PhotoAvatar : IDisposable {
 readonly Bitmap portrait; readonly Bitmap[] mouths=new Bitmap[6],eyes=new Bitmap[4];
 readonly NarrationAudio voice; readonly Stopwatch clock=Stopwatch.StartNew();
 double lastFrame; float opening;
 internal PhotoAvatar(int person,int color,NarrationAudio audio){
  if(person!=0||color!=0)throw new ArgumentException("Bản thử chuyển động chỉ hỗ trợ tóc dài, áo đen.");
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
   for(int i=1;i<4;i++)eyes[i]=Patch(atlas,centers[i],875,132,103,54,21,sx,sy);
  }
 }
 // Precompute soft, registered patches once. No skin recoloring or painted teeth.
 Bitmap Patch(Bitmap atlas,double sourceX,double sourceY,int cx,int cy,int rx,int ry,double sx,double sy){
  var result=(Bitmap)portrait.Clone();
  for(int y=cy-ry;y<=cy+ry;y++)for(int x=cx-rx;x<=cx+rx;x++){
   double u=(x-cx)/(double)rx,v=(y-cy)/(double)ry,q=Math.Sqrt(u*u+v*v);
   if(q>=1)continue;
   double alpha=Math.Min(1,(1-q)/.35);alpha=alpha*alpha*(3-2*alpha);
   Color source=Sample(atlas,(sourceX+x-cx)*sx,(sourceY+y-cy)*sy);
   result.SetPixel(x,y,Mix(portrait.GetPixel(x,y),source,alpha));
  }
  return result;
 }
 static Color Sample(Bitmap b,double x,double y){
  x=Math.Max(0,Math.Min(b.Width-1,x));y=Math.Max(0,Math.Min(b.Height-1,y));
  int ix=(int)x,iy=(int)y;
  return Mix(Mix(b.GetPixel(ix,iy),b.GetPixel(Math.Min(ix+1,b.Width-1),iy),x-ix),Mix(b.GetPixel(ix,Math.Min(iy+1,b.Height-1)),b.GetPixel(Math.Min(ix+1,b.Width-1),Math.Min(iy+1,b.Height-1)),x-ix),y-iy);
 }
 internal static float Blink(double seconds){double t=seconds%4.6;return t<4.28?0:(float)Math.Sin((t-4.28)/.32*Math.PI);}
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
  BlendTrack(result,eyes,Math.Max(0,Math.Min(1,blink))*3,new Rectangle(78,82,109,43));
  return result;
 }
 static void BlendTrack(Bitmap dst,Bitmap[] track,double phase,Rectangle region){
  if(phase<=0)return;
  int first=Math.Min(track.Length-1,(int)phase),next=Math.Min(track.Length-1,first+1);
  double t=phase-first;t=t*t*(3-2*t);
  for(int y=region.Top;y<region.Bottom;y++)for(int x=region.Left;x<region.Right;x++)dst.SetPixel(x,y,Mix(track[first].GetPixel(x,y),track[next].GetPixel(x,y),t));
 }
 static Color Mix(Color a,Color b,double t){return Color.FromArgb(Channel(a.R+(b.R-a.R)*t),Channel(a.G+(b.G-a.G)*t),Channel(a.B+(b.B-a.B)*t));}
 static int Channel(double n){return Math.Max(0,Math.Min(255,(int)Math.Round(n)));}
 public void Dispose(){portrait.Dispose();foreach(var b in mouths)if(b!=null)b.Dispose();foreach(var b in eyes)if(b!=null)b.Dispose();}
}
