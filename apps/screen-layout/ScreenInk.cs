using System;
using System.Collections.Generic;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.Windows.Forms;

// Separate input shield keeps the visible ink click-through in normal mouse mode.
internal sealed class ScreenInk : IDisposable {
 internal sealed class Stroke { internal readonly List<Point> Points=new List<Point>();internal Color Color;internal float Width;internal int Tool; }
 sealed class Canvas : Form {
  internal ScreenInk Ink;
  protected override CreateParams CreateParams {get{var p=base.CreateParams;p.ExStyle|=0x20|0x08000000;return p;}}
  protected override bool ShowWithoutActivation{get{return true;}}
  internal Canvas(){FormBorderStyle=FormBorderStyle.None;ShowInTaskbar=false;StartPosition=FormStartPosition.Manual;TopMost=true;BackColor=Color.Fuchsia;TransparencyKey=BackColor;DoubleBuffered=true;}
  protected override void OnPaint(PaintEventArgs e){base.OnPaint(e);Ink.Paint(e.Graphics);}
 }
 sealed class Shield : Form {
  internal Shield(){FormBorderStyle=FormBorderStyle.None;ShowInTaskbar=false;StartPosition=FormStartPosition.Manual;TopMost=true;BackColor=Color.Black;Opacity=.01;KeyPreview=true;Cursor=Cursors.Cross;}
 }
 sealed class Particle {internal float X,Y;internal DateTime Born;internal bool Heart;}
 readonly Canvas canvas=new Canvas();readonly Shield shield=new Shield();readonly Form bar=new Form();
 readonly List<Stroke> strokes=new List<Stroke>();readonly List<Particle> particles=new List<Particle>();
 readonly Timer animation=new Timer{Interval=33};readonly ToolTip tips=new ToolTip();readonly Random random=new Random();
 readonly List<Button> modes=new List<Button>();Stroke current;int tool;Color color=Color.Red;float width=3;bool disposed;Rectangle area;
 internal int StrokeCount{get{return strokes.Count;}}internal int ParticleCount{get{return particles.Count;}}internal bool Drawing{get{return shield.Visible;}}
 internal ScreenInk(){
  canvas.Ink=this;bar.FormBorderStyle=FormBorderStyle.None;bar.ShowInTaskbar=false;bar.StartPosition=FormStartPosition.Manual;bar.TopMost=true;bar.BackColor=Color.FromArgb(27,44,51);bar.ClientSize=new Size(444,42);bar.Font=new Font("Segoe UI",10);bar.KeyPreview=true;
  string[] labels={"↖","Bút","／","→"};string[] help={"Dùng chuột bình thường (Esc)","Vẽ tự do","Đường thẳng","Mũi tên"};
  for(int i=0;i<4;i++){int mode=i;var b=Button(labels[i],i*44,44,help[i]);modes.Add(b);b.Click+=delegate{SelectTool(mode);};}
  var palette=Button("Màu",176,48,"Chọn màu nét");palette.ForeColor=color;palette.Click+=delegate{using(var d=new ColorDialog{Color=color,FullOpen=true}){if(d.ShowDialog(bar)==DialogResult.OK){color=d.Color;palette.ForeColor=color;}}};
  var size=Button("3 px",224,52,"Độ dày: 3 / 6 / 10 px");size.Click+=delegate{width=width==3?6:width==6?10:3;size.Text=width+" px";};
  Button("Xoá",276,48,"Xoá tất cả nét và hiệu ứng").Click+=delegate{Clear();};
  Button("♥",324,40,"Thả tim bay lên").Click+=delegate{Burst(true);};Button("✿",364,40,"Thả hoa bay lên").Click+=delegate{Burst(false);};
  Button("—",404,40,"Ẩn thanh bút; mở lại trong Tùy chọn").Click+=delegate{SelectTool(0);bar.Hide();};
  shield.MouseDown+=delegate(object s,MouseEventArgs e){if(e.Button!=MouseButtons.Left)return;Begin(e.Location);shield.Capture=true;};
  shield.MouseMove+=delegate(object s,MouseEventArgs e){if(current!=null)Move(e.Location);};
  shield.MouseUp+=delegate{current=null;shield.Capture=false;};shield.MouseCaptureChanged+=delegate{if(!shield.Capture)current=null;};
  KeyEventHandler escape=delegate(object s,KeyEventArgs e){if(e.KeyCode==Keys.Escape){SelectTool(0);e.Handled=true;}};shield.KeyDown+=escape;bar.KeyDown+=escape;
  animation.Tick+=delegate{particles.RemoveAll(delegate(Particle p){return (DateTime.UtcNow-p.Born).TotalSeconds>2.5;});canvas.Invalidate();if(particles.Count==0)animation.Stop();};
 }
 Button Button(string text,int x,int w,string tip){var b=new Button{Text=text,Location=new Point(x+2,3),Size=new Size(w-4,36),FlatStyle=FlatStyle.Flat,ForeColor=Color.White,BackColor=Color.FromArgb(36,60,68),TabStop=false};b.FlatAppearance.BorderSize=0;bar.Controls.Add(b);tips.SetToolTip(b,tip);return b;}
 internal void Show(Rectangle bounds){SetArea(bounds);if(!canvas.Visible)canvas.Show();bar.Show();SelectTool(0);}
 internal void SetArea(Rectangle bounds){if(disposed||bounds.Width<100||bounds.Height<100)return;area=bounds;canvas.Bounds=shield.Bounds=area;bar.Location=new Point(Math.Max(area.Left,area.Right-bar.Width-12),area.Bottom-bar.Height-12);}
 internal void SelectTool(int value){tool=value;current=null;shield.Capture=false;for(int i=0;i<modes.Count;i++)modes[i].BackColor=i==tool?Color.Teal:Color.FromArgb(36,60,68);if(tool==0)shield.Hide();else{shield.Show();shield.Activate();}canvas.BringToFront();bar.BringToFront();}
 internal void Begin(Point p){if(tool==0)return;current=new Stroke{Color=color,Width=width,Tool=tool};current.Points.Add(p);current.Points.Add(p);strokes.Add(current);canvas.Invalidate();}
 internal void Move(Point p){if(current==null)return;if(current.Tool==1)current.Points.Add(p);else current.Points[1]=p;canvas.Invalidate();}
 internal void Clear(){current=null;strokes.Clear();particles.Clear();animation.Stop();canvas.Invalidate();SelectTool(0);}
 internal void Burst(bool heart){for(int i=0;i<7;i++)particles.Add(new Particle{X=area.Width-60-random.Next(Math.Max(1,Math.Min(260,area.Width-80))),Y=area.Height-65+random.Next(30),Born=DateTime.UtcNow.AddMilliseconds(random.Next(200)),Heart=heart});if(particles.Count>70)particles.RemoveRange(0,particles.Count-70);animation.Start();canvas.Invalidate();}
 void Paint(Graphics g){g.SmoothingMode=SmoothingMode.AntiAlias;foreach(var s in strokes){using(var pen=new Pen(s.Color,s.Width)){pen.StartCap=LineCap.Round;pen.EndCap=LineCap.Round;if(s.Tool==3){using(var cap=new AdjustableArrowCap(4,5,true)){pen.CustomEndCap=cap;g.DrawLine(pen,s.Points[0],s.Points[1]);}}else g.DrawLines(pen,s.Points.ToArray());}}
  foreach(var p in particles){float age=(float)(DateTime.UtcNow-p.Born).TotalSeconds;if(age<0)continue;float x=p.X+(float)Math.Sin(age*4)*18,y=p.Y-age*130;using(var font=new Font("Segoe UI Symbol",28)){using(var brush=new SolidBrush(p.Heart?Color.HotPink:Color.Gold)){g.DrawString(p.Heart?"♥":"✿",font,brush,x,y);}}}
 }
 public void Dispose(){if(disposed)return;disposed=true;animation.Stop();animation.Dispose();tips.Dispose();shield.Dispose();canvas.Dispose();bar.Dispose();strokes.Clear();particles.Clear();}
}
