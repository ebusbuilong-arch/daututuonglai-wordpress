using System;
using System.Drawing;
using System.Drawing.Imaging;
using System.Diagnostics;
using System.IO;

// Lightweight local animation. Audio amplitude drives the mouth, not phoneme recognition.
internal sealed class PhotoAvatar : IDisposable {
 readonly Bitmap portrait;readonly NarrationAudio voice;readonly Stopwatch clock=Stopwatch.StartNew();readonly PointF mouth,leftEye,rightEye;float opening;
 internal PhotoAvatar(int person,int color,NarrationAudio audio){
  if(person<0||person>2||color<0||color>3)throw new ArgumentOutOfRangeException();voice=audio;
  int[] tops={0,288,600},bottoms={285,596,941};
  using(var sheet=new Bitmap(Path.Combine(AppDomain.CurrentDomain.BaseDirectory,"avatars.jpg"))){portrait=sheet.Clone(new Rectangle(color*418+2,tops[person]+2,414,bottoms[person]-tops[person]-4),PixelFormat.Format24bppRgb);}
  float shift=color==3?-3:color==0?0:-1;
  PointF[] mouths={new PointF(249,99),new PointF(241,114),new PointF(235,123)};
  PointF[] left={new PointF(228,74),new PointF(217,88),new PointF(210,95)};
  PointF[] right={new PointF(257,66),new PointF(248,79),new PointF(244,85)};
  mouth=new PointF(mouths[person].X+shift-2,mouths[person].Y-2);leftEye=new PointF(left[person].X+shift-2,left[person].Y-2);rightEye=new PointF(right[person].X+shift-2,right[person].Y-2);
 }
 internal static float Blink(double seconds){double t=seconds%4.6;return t<4.28?0:(float)Math.Sin((t-4.28)/.32*Math.PI);}
 internal Bitmap Take(){float target=voice==null?0:voice.Level;opening=target==0?0:opening*.35f+target*.65f;return Render(opening,Blink(clock.Elapsed.TotalSeconds));}
 internal Bitmap Render(float amplitude,float blink){
  var result=(Bitmap)portrait.Clone();amplitude=Math.Max(0,Math.Min(1,amplitude));blink=Math.Max(0,Math.Min(1,blink));
  if(amplitude>.015f)Warp(result,mouth,19,17,amplitude*5.0f,false);
  if(blink>.01f){Warp(result,leftEye,12,10,blink,true);Warp(result,rightEye,12,10,blink,true);}return result;
 }
 void Warp(Bitmap dst,PointF center,int rx,int ry,float amount,bool eye){
  // Work in coordinates aligned with the gently tilted face; preserve the rest of the portrait exactly.
  double angle=-.25,c=Math.Cos(angle),s=Math.Sin(angle);
  for(int y=(int)center.Y-ry-5;y<=center.Y+ry+5;y++)for(int x=(int)center.X-rx-5;x<=center.X+rx+5;x++){
   if(x<0||y<0||x>=portrait.Width||y>=portrait.Height)continue;
   double dx=x-center.X,dy=y-center.Y,u=dx*c+dy*s,v=-dx*s+dy*c,q=u*u/(rx*rx)+v*v/(ry*ry);if(q>=1)continue;
   double edge=Math.Max(0,1-q),sourceV=v;Color sample;
   if(eye){
    // Cover the iris with eyelid texture sampled immediately above it; keep a thin lash at closure.
    double lid=-4+amount*8;
    if(Math.Abs(u)<8&&v<lid&&v>-5){sourceV=-5+(v+5)*.15;}
    if(amount>.85&&Math.Abs(u)<8&&Math.Abs(v-2)<.65){sample=Color.FromArgb(72,47,39);dst.SetPixel(x,y,sample);continue;}
   }else{
    double gap=amount*Math.Max(0,1-u*u/225.0);
    if(Math.Abs(u)<15&&Math.Abs(v)<gap*.5){
     sample=v<-gap*.1?Color.FromArgb(218,194,183):Color.FromArgb(67,28,30);dst.SetPixel(x,y,sample);continue;
    }
    sourceV=v-Math.Sign(v)*gap*.5*edge;
   }
   int sx=(int)Math.Round(center.X+u*c-sourceV*s),sy=(int)Math.Round(center.Y+u*s+sourceV*c);
   sx=Math.Max(0,Math.Min(portrait.Width-1,sx));sy=Math.Max(0,Math.Min(portrait.Height-1,sy));sample=portrait.GetPixel(sx,sy);
   Color original=portrait.GetPixel(x,y);double blend=Math.Min(1,edge*5);dst.SetPixel(x,y,Color.FromArgb((int)(original.R*(1-blend)+sample.R*blend),(int)(original.G*(1-blend)+sample.G*blend),(int)(original.B*(1-blend)+sample.B*blend)));
  }
 }
 public void Dispose(){portrait.Dispose();}
}
