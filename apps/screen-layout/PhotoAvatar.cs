using System;
using System.Drawing;
using System.Drawing.Imaging;
using System.Diagnostics;
using System.IO;

// Lightweight local animation. Audio amplitude drives the mouth, not phoneme recognition.
internal sealed class PhotoAvatar : IDisposable {
 readonly Bitmap portrait;readonly NarrationAudio voice;readonly Stopwatch clock=Stopwatch.StartNew();readonly PointF mouth,leftEye,rightEye;float opening;
 readonly Bitmap expressionAtlas; double lastFrame;
 internal PhotoAvatar(int person,int color,NarrationAudio audio){
  if(person<0||person>2||color<0||color>3)throw new ArgumentOutOfRangeException();voice=audio;
  int[] tops={0,288,600},bottoms={285,596,941};
  using(var sheet=new Bitmap(Path.Combine(AppDomain.CurrentDomain.BaseDirectory,"avatars.jpg"))){portrait=sheet.Clone(new Rectangle(color*418+2,tops[person]+2,414,bottoms[person]-tops[person]-4),PixelFormat.Format24bppRgb);}
  string expressions=Path.Combine(AppDomain.CurrentDomain.BaseDirectory,"face-transitions.png");
  if(File.Exists(expressions))expressionAtlas=new Bitmap(expressions);
  float shift=color==3?-3:color==0?0:-1;
  PointF[] mouths={new PointF(249,99),new PointF(241,114),new PointF(235,123)};
  PointF[] left={new PointF(228,74),new PointF(217,88),new PointF(210,95)};
  PointF[] right={new PointF(257,66),new PointF(248,79),new PointF(244,85)};
  mouth=new PointF(mouths[person].X+shift-2,mouths[person].Y-2);leftEye=new PointF(left[person].X+shift-2,left[person].Y-2);rightEye=new PointF(right[person].X+shift-2,right[person].Y-2);
 }
 internal static float Blink(double seconds){double t=seconds%4.6;return t<4.28?0:(float)Math.Sin((t-4.28)/.32*Math.PI);}
 internal Bitmap Take(){
  double now=clock.Elapsed.TotalSeconds,dt=Math.Min(.1,Math.Max(0,now-lastFrame));lastFrame=now;
  float target=voice==null?0:voice.Level;
  // Frame-rate-independent attack/release; silence closes rather than freezing the mouth.
  double tau=target>opening?.055:.085;
  opening+=(target-opening)*(float)(1-Math.Exp(-dt/tau));
  if(opening<.005f)opening=0;
  return Render(opening,Blink(now));
 }
 internal Bitmap Render(float amplitude,float blink){
  var result=(Bitmap)portrait.Clone();amplitude=Math.Max(0,Math.Min(1,amplitude));blink=Math.Max(0,Math.Min(1,blink));
  if(expressionAtlas!=null){
   // Mouth and eyelids have independent tracks. Never swap the entire portrait.
   BlendPatch(result,mouth,20,19,0,amplitude*5,.50,.60,.18,.19);
   PointF eyes=new PointF((leftEye.X+rightEye.X)/2,(leftEye.Y+rightEye.Y)/2);
   BlendPatch(result,eyes,30,13,3,blink*3,.50,.39,.28,.13);
  }else{
   // Compatibility with older private bundles that do not include the expression atlas.
   if(amplitude>.015f)Warp(result,mouth,19,17,amplitude*5.0f,false);
   if(blink>.01f){Warp(result,leftEye,12,10,blink,true);Warp(result,rightEye,12,10,blink,true);}
  }
  return result;
 }
 // Bilinear sampling prevents stair-stepping as intermediate frames are blended.
 Color Sample(int frame,int row,double u,double v){
  int left=frame*expressionAtlas.Width/6,right=(frame+1)*expressionAtlas.Width/6;
  int top=row*expressionAtlas.Height/4,bottom=(row+1)*expressionAtlas.Height/4;
  double x=left+Math.Max(0,Math.Min(1,u))*(right-left-1),y=top+Math.Max(0,Math.Min(1,v))*(bottom-top-1);
  int x0=(int)x,y0=(int)y,x1=Math.Min(right-1,x0+1),y1=Math.Min(bottom-1,y0+1);
  Color a=Mix(expressionAtlas.GetPixel(x0,y0),expressionAtlas.GetPixel(x1,y0),x-x0);
  Color b=Mix(expressionAtlas.GetPixel(x0,y1),expressionAtlas.GetPixel(x1,y1),x-x0);
  return Mix(a,b,y-y0);
 }
 static Color Mix(Color a,Color b,double t){return Color.FromArgb(Channel(a.R+(b.R-a.R)*t),Channel(a.G+(b.G-a.G)*t),Channel(a.B+(b.B-a.B)*t));}
 static int Channel(double n){return Math.Max(0,Math.Min(255,(int)Math.Round(n)));}
 void BlendPatch(Bitmap dst,PointF center,int rx,int ry,int row,double phase,double cx,double cy,double sx,double sy){
  int first=Math.Min(5,(int)phase),next=Math.Min(5,first+1);double t=phase-first;
  double c=Math.Cos(-.25),s=Math.Sin(-.25);
  // Match surrounding skin tone, while retaining actual lip and eyelid texture.
  Color sourceSkin=Sample(0,row,cx,cy-sy*.8);
  int skinX=Math.Max(0,Math.Min(portrait.Width-1,(int)center.X));
  int skinY=Math.Max(0,Math.Min(portrait.Height-1,(int)center.Y-ry+2));
  Color skin=portrait.GetPixel(skinX,skinY);
  for(int y=Math.Max(0,(int)center.Y-ry-rx/3);y<Math.Min(dst.Height,center.Y+ry+rx/3);y++)
   for(int x=Math.Max(0,(int)center.X-rx-ry/3);x<Math.Min(dst.Width,center.X+rx+ry/3);x++){
    double dx=x-center.X,dy=y-center.Y,u=(dx*c+dy*s)/rx,v=(-dx*s+dy*c)/ry;
    double q=u*u+v*v;if(q>=1)continue;
    Color texture=Mix(Sample(first,row,cx+u*sx,cy+v*sy),Sample(next,row,cx+u*sx,cy+v*sy),t);
    texture=Color.FromArgb(Channel(texture.R+(skin.R-sourceSkin.R)*.65),Channel(texture.G+(skin.G-sourceSkin.G)*.65),Channel(texture.B+(skin.B-sourceSkin.B)*.65));
    double alpha=Math.Min(1,(1-q)*4);alpha=alpha*alpha*(3-2*alpha);
    dst.SetPixel(x,y,Mix(dst.GetPixel(x,y),texture,alpha));
   }
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
 public void Dispose(){portrait.Dispose();if(expressionAtlas!=null)expressionAtlas.Dispose();}
}
