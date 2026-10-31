using System;
using System.Collections.Generic;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.IO;
using System.Runtime.InteropServices;
using System.Windows.Forms;

static class VirtualNative {
 [DllImport("user32.dll")] internal static extern bool SetCursorPos(int x,int y);
 [DllImport("user32.dll",EntryPoint="ClipCursor")] internal static extern bool Clip(ref Native.Rect r);
 [DllImport("user32.dll",EntryPoint="ClipCursor")] internal static extern bool Unclip(IntPtr r);
 [DllImport("user32.dll")] internal static extern IntPtr WindowFromPoint(Native.Point p);
 [DllImport("user32.dll")] internal static extern IntPtr GetAncestor(IntPtr h,uint flag);
 [DllImport("user32.dll")] internal static extern bool SetForegroundWindow(IntPtr h);
}
static class ViewGeometry {
 internal static Rectangle Fit(Rectangle cell,Size source){
  if(source.Width<=0||source.Height<=0||cell.Width<=0||cell.Height<=0)return Rectangle.Empty;
  double scale=Math.Min((double)cell.Width/source.Width,(double)cell.Height/source.Height);
  int w=Math.Max(1,(int)(source.Width*scale)),h=Math.Max(1,(int)(source.Height*scale));
  return new Rectangle(cell.X+(cell.Width-w)/2,cell.Y+(cell.Height-h)/2,w,h);
 }
 internal static Point ToSource(Point p,Rectangle view,Rectangle source){
  int x=source.Left+(int)((long)(p.X-view.Left)*source.Width/Math.Max(1,view.Width));
  int y=source.Top+(int)((long)(p.Y-view.Top)*source.Height/Math.Max(1,view.Height));
  return new Point(Math.Max(source.Left,Math.Min(source.Right-1,x)),Math.Max(source.Top,Math.Min(source.Bottom-1,y)));
 }
 internal static Point ToView(Point p,Rectangle source,Rectangle view){return new Point(view.Left+(int)((long)(p.X-source.Left)*view.Width/Math.Max(1,source.Width)),view.Top+(int)((long)(p.Y-source.Top)*view.Height/Math.Max(1,source.Height)));}
}
// Driver-free workspace: the selected application remains a normal native window.
internal sealed class VirtualDesktopDock : Form {
 const uint Callback=0x8101;
 readonly Button options=new Button{Text="Tùy chọn ▾",Size=new Size(120,32),Location=new Point(8,5),FlatStyle=FlatStyle.Flat};
 readonly Button quit=new Button{Text="×",Size=new Size(32,32),FlatStyle=FlatStyle.Flat};
 readonly Button webcamButton=new Button{Text="Bật webcam",Size=new Size(115,28),Location=new Point(12,48),FlatStyle=FlatStyle.Flat};
 readonly Timer cameraTimer=new Timer{Interval=66},settle=new Timer{Interval=250},maintenance=new Timer{Interval=750};
 readonly NotifyIcon tray=new NotifyIcon{Icon=SystemIcons.Application,Text="DTTL • Không gian làm việc 0.5"};
 readonly ShellNative.WinEvent moveEvent;
 IntPtr hook;DockedWindow app,beforeMove;WebcamFeed webcam;Bitmap webcamImage;BroadcastForm broadcast;
 string webcamExe=Path.Combine(AppDomain.CurrentDomain.BaseDirectory,"ffmpeg.exe"),webcamStatus="Webcam đang tắt",appStatus="Tùy chọn → Chọn Zalo / ứng dụng";
 string host;bool registered,closing,placing;uint restartMessage;
 internal VirtualDesktopDock():this(true){}
 internal VirtualDesktopDock(bool unused){
  Text="DTTL • Không gian làm việc 0.5";FormBorderStyle=FormBorderStyle.None;ShowInTaskbar=false;StartPosition=FormStartPosition.Manual;AutoScaleMode=AutoScaleMode.None;DoubleBuffered=true;
  BackColor=Color.FromArgb(20,31,37);ForeColor=Color.FromArgb(235,245,247);Font=new Font("Segoe UI",9);
  foreach(var button in new[]{options,quit,webcamButton}){button.ForeColor=Color.White;button.BackColor=Color.FromArgb(40,65,73);button.FlatAppearance.BorderColor=Color.FromArgb(76,116,124);button.FlatAppearance.MouseOverBackColor=Color.FromArgb(55,93,102);button.FlatAppearance.MouseDownBackColor=Color.FromArgb(35,120,123);Controls.Add(button);}
  options.Click+=delegate{Menu().Show(options,new Point(0,options.Height));};quit.Click+=delegate{Close();};webcamButton.Click+=delegate{ToggleWebcam();};
  host=Screen.PrimaryScreen.DeviceName;
  moveEvent=delegate(IntPtr h,uint ev,IntPtr window,int obj,int child,uint thread,uint time){
   if(closing||obj!=0||!IsHandleCreated)return;
   if(ev==0xA){beforeMove=DesktopDock.Capture(window);return;}
   if(ev!=0xB)return;
   var saved=beforeMove!=null&&beforeMove.Handle==window?beforeMove:DesktopDock.Capture(window);beforeMove=null;
   BeginInvoke((Action)delegate{if(closing)return;Native.Point p;if(!ShellNative.GetCursorPos(out p))return;
    var target=ApplicationArea;target.Inflate(12,0);
    if(target.Contains(p.X,p.Y)){if(DesktopDock.Valid(saved))Attach(saved);}
    else if(app!=null&&app.Handle==window)ReleaseApp(false);
   });
  };
  Shown+=delegate{restartMessage=ShellNative.RegisterWindowMessage("TaskbarCreated");RegisterBar();hook=ShellNative.SetWinEventHook(0xA,0xB,IntPtr.Zero,moveEvent,0,0,2);tray.Visible=true;tray.MouseClick+=delegate{Menu().Show(Cursor.Position);};maintenance.Start();};
  Activated+=delegate{if(!closing&&DesktopDock.Valid(app))BeginInvoke((Action)delegate{RaiseApp();});};
  settle.Tick+=delegate{settle.Stop();FitApp();};
  maintenance.Tick+=delegate{if(app!=null&&!DesktopDock.Valid(app)){app=null;Invalidate();}RaiseApp();};
  cameraTimer.Tick+=delegate{if(webcam==null)return;var next=webcam.Take();if(next!=null){if(webcamImage!=null)webcamImage.Dispose();webcamImage=next;Invalidate(Pane(ClientRectangle,0));}if(webcam.Ended){StopWebcam();webcamStatus="Webcam đã dừng. Kiểm tra kết nối/quyền Camera.";Invalidate();}};
  FormClosed+=delegate{Shutdown();};
 }
 internal static Rectangle Pane(Rectangle client,int index){int top=client.Top+42,h=Math.Max(1,client.Height-42),split=top+h/3;return index==0?Rectangle.FromLTRB(client.Left+8,top+5,client.Right-8,split-5):Rectangle.FromLTRB(client.Left+8,split+5,client.Right-8,client.Bottom-8);}
 internal Rectangle ApplicationArea{get{var r=Pane(ClientRectangle,1);r.Offset(Left,Top);return r;}}
 Rectangle WebcamArea(){var r=Pane(ClientRectangle,0);r.Y+=36;r.Height=Math.Max(1,r.Height-36);return r;}
 ShellNative.AppBar Data(){return new ShellNative.AppBar{Size=(uint)Marshal.SizeOf(typeof(ShellNative.AppBar)),Window=Handle,Callback=Callback,Edge=2};}
 void RegisterBar(){if(closing||registered)return;var d=Data();registered=ShellNative.SHAppBarMessage(0,ref d)!=UIntPtr.Zero;if(registered)Place();else{MessageBox.Show("Không giữ được vùng phụ.");Close();}}
 void Place(){
  if(!registered||closing||placing)return;placing=true;
  try{Screen monitor=null;foreach(var s in Screen.AllScreens)if(s.DeviceName==host)monitor=s;if(monitor==null){ReleaseApp(true);monitor=Screen.PrimaryScreen;host=monitor.DeviceName;}
   var b=monitor.Bounds;int width=b.Width*33/100;var d=Data();d.Rect=new Native.Rect{L=b.Right-width,T=b.Top,R=b.Right,B=b.Bottom};ShellNative.SHAppBarMessage(2,ref d);d.Rect.L=d.Rect.R-width;ShellNative.SHAppBarMessage(3,ref d);
   SetBounds(d.Rect.L,d.Rect.T,d.Rect.R-d.Rect.L,d.Rect.B-d.Rect.T);quit.Location=new Point(ClientSize.Width-40,5);Native.SetWindowPos(Handle,new IntPtr(-1),0,0,0,0,0x13);
   if(DesktopDock.Valid(app)){settle.Stop();settle.Start();}Invalidate();
  }finally{placing=false;}
 }
 void Retire(ContextMenuStrip menu){menu.Closed+=delegate{if(!closing&&IsHandleCreated)BeginInvoke((Action)delegate{menu.Dispose();});};}
 ContextMenuStrip Menu(){
  var m=new ContextMenuStrip{BackColor=Color.White,ForeColor=Color.FromArgb(20,30,35)};
  m.Items.Add("33% • Webcam 1/3 • Ứng dụng 2/3").Enabled=false;
  m.Items.Add("Chọn Zalo / ứng dụng…",null,delegate{BeginInvoke((Action)delegate{if(!closing)PickWindow();});});
  var show=m.Items.Add("Căn lại / Hiện ứng dụng",null,delegate{if(DesktopDock.Valid(app)){Native.ShowWindowAsync(app.Handle,9);settle.Start();}});show.Enabled=DesktopDock.Valid(app);
  var release=m.Items.Add("Trả ứng dụng về vị trí cũ",null,delegate{ReleaseApp(true);});release.Enabled=DesktopDock.Valid(app);
  m.Items.Add(new ToolStripSeparator());
  m.Items.Add("Quay / Livestream…",null,delegate{BeginInvoke((Action)delegate{if(closing)return;if(broadcast==null||broadcast.IsDisposed)broadcast=new BroadcastForm();broadcast.Show();broadcast.Activate();});});
  m.Items.Add("Hướng dẫn",null,delegate{BeginInvoke((Action)delegate{if(!closing)MessageBox.Show(this,"Không cần driver màn hình ảo.\n\nVùng phụ rộng 33%; webcam trên 1/3 và cửa sổ ứng dụng dưới 2/3.\nTùy chọn → Chọn Zalo / ứng dụng, hoặc kéo thanh tiêu đề cửa sổ vào ô dưới. Chat, cuộn và gõ trực tiếp. Kéo ra ngoài để bỏ ghim.\n\nBật webcam cần ffmpeg.exe và quyền Camera của Windows. Webcam chỉ xem tại máy cho đến khi anh chủ động quay/phát màn hình chứa nó.\n\nNếu ứng dụng không cho thu nhỏ đủ, app sẽ trả lại vị trí cũ và báo rõ. Kích thước vật lý 32 inch không quyết định số điểm ảnh; độ phân giải và Scale cũng ảnh hưởng.\n\nĐóng DTTL sẽ trả cửa sổ và vùng desktop. Driver đã cài trước đó không tự gỡ.",Text);});});
  m.Items.Add("Thoát",null,delegate{BeginInvoke((Action)delegate{Close();});});Retire(m);return m;
 }
 void PickWindow(){
  var menu=new ContextMenuStrip();Native.EnumWindows(delegate(IntPtr h,IntPtr unused){var w=DesktopDock.Capture(h);if(!DesktopDock.Valid(w))return true;int cloaked;if(Native.Cloaked(h,14,out cloaked,4)==0&&cloaked!=0)return true;
   var title=new System.Text.StringBuilder(256);Native.GetWindowText(h,title,title.Capacity);menu.Items.Add(title.ToString(),null,delegate{Attach(w);});return true;},IntPtr.Zero);
  if(menu.Items.Count==0)menu.Items.Add("Mở Zalo / ứng dụng trước").Enabled=false;Retire(menu);menu.Show(this,new Point(8,40));
 }
 internal void Attach(DockedWindow window){
  if(!DesktopDock.Valid(window))return;
  if(app==null||app.Handle!=window.Handle){ReleaseApp(true);app=window;}
  appStatus="";Native.ShowWindowAsync(app.Handle,9);settle.Stop();settle.Start();Invalidate();
 }
 void RaiseApp(){if(!closing&&DesktopDock.Valid(app)&&!ShellNative.IsIconic(app.Handle))Native.SetWindowPos(app.Handle,new IntPtr(-1),0,0,0,0,0x13);}
 void FitApp(){
  if(closing||!DesktopDock.Valid(app))return;var wanted=ApplicationArea;Native.Rect outer,visible;int l=0,t=0,r=0,b=0;
  if(Native.GetWindowRect(app.Handle,out outer)&&Native.DwmGetWindowAttribute(app.Handle,9,out visible,Marshal.SizeOf(typeof(Native.Rect)))==0){l=visible.L-outer.L;t=visible.T-outer.T;r=outer.R-visible.R;b=outer.B-visible.B;}
  bool ok=Native.SetWindowPos(app.Handle,new IntPtr(-1),wanted.X-l,wanted.Y-t,wanted.Width+l+r,wanted.Height+t+b,0x4010);
  var expected=app;var verify=new Timer{Interval=200};verify.Tick+=delegate{verify.Stop();verify.Dispose();if(closing||app!=expected||!DesktopDock.Valid(expected))return;
   Native.Rect actual;if(Native.DwmGetWindowAttribute(expected.Handle,9,out actual,Marshal.SizeOf(typeof(Native.Rect)))!=0)Native.GetWindowRect(expected.Handle,out actual);
   if(!ok||Math.Abs(actual.L-wanted.Left)>12||Math.Abs(actual.T-wanted.Top)>12||Math.Abs(actual.R-wanted.Right)>12||Math.Abs(actual.B-wanted.Bottom)>12){ReleaseApp(true);appStatus="Ứng dụng chưa thu vừa ô. Kiểm tra độ phân giải / Scale.";tray.ShowBalloonTip(6000,"Ứng dụng không vừa ô","Đã trả cửa sổ về chỗ cũ. Ứng dụng có giới hạn kích thước hoặc không cho điều chỉnh.",ToolTipIcon.Info);Invalidate();}
  };verify.Start();
 }
 internal void ReleaseApp(bool restore){settle.Stop();var old=app;app=null;if(DesktopDock.Valid(old)){Native.SetWindowPos(old.Handle,new IntPtr(old.WasTopMost?-1:-2),0,0,0,0,0x13);if(restore){var p=old.Original;Native.SetWindowPlacement(old.Handle,ref p);}}appStatus="Tùy chọn → Chọn Zalo / ứng dụng";Invalidate();}
 void StopWebcam(){cameraTimer.Stop();if(webcam!=null){webcam.Dispose();webcam=null;}if(webcamImage!=null){webcamImage.Dispose();webcamImage=null;}webcamButton.Text="Bật webcam";Invalidate();}
 void ToggleWebcam(){
  if(webcam!=null){StopWebcam();webcamStatus="Webcam đang tắt";return;}
  using(var picker=new WebcamPicker(webcamExe)){if(picker.ShowDialog(this)!=DialogResult.OK)return;webcamExe=picker.Encoder;
   try{webcam=new WebcamFeed();webcam.Start(webcamExe,picker.Device);webcamButton.Text="Tắt webcam";webcamStatus="Đang mở webcam…";cameraTimer.Start();}
   catch{StopWebcam();webcamStatus="Không mở được webcam. Kiểm tra quyền Camera và ứng dụng đang dùng camera.";}
  }Invalidate();
 }
 protected override void OnPaint(PaintEventArgs e){
  base.OnPaint(e);var upper=Pane(ClientRectangle,0);var lower=Pane(ClientRectangle,1);
  using(var fill=new SolidBrush(Color.FromArgb(25,41,47))){e.Graphics.FillRectangle(fill,upper);e.Graphics.FillRectangle(fill,lower);}
  using(var border=new Pen(Color.FromArgb(54,86,93))){e.Graphics.DrawRectangle(border,upper);e.Graphics.DrawRectangle(border,lower);}
  int split=42+(ClientSize.Height-42)/3;
  using(var line=new Pen(Color.FromArgb(59,139,143))){e.Graphics.DrawLine(line,14,split,Width-14,split);e.Graphics.DrawLine(line,1,6,1,Height-6);}
  using(var grip=new Pen(Color.FromArgb(116,212,203),3)){e.Graphics.DrawLine(grip,Width/2-15,split,Width/2+15,split);}
  if(webcamImage!=null){e.Graphics.InterpolationMode=InterpolationMode.Bilinear;e.Graphics.DrawImage(webcamImage,ViewGeometry.Fit(WebcamArea(),webcamImage.Size));}
  else TextRenderer.DrawText(e.Graphics,webcamStatus,Font,WebcamArea(),ForeColor,TextFormatFlags.HorizontalCenter|TextFormatFlags.VerticalCenter|TextFormatFlags.WordBreak);
  if(!DesktopDock.Valid(app))TextRenderer.DrawText(e.Graphics,appStatus,Font,lower,ForeColor,TextFormatFlags.HorizontalCenter|TextFormatFlags.VerticalCenter|TextFormatFlags.WordBreak);
 }
 internal void ReleaseInput(){} // No cursor clipping or forwarding in the driver-free version.
 protected override void WndProc(ref Message m){
  if(m.Msg==Callback&&m.WParam.ToInt32()==1&&!placing&&!closing)BeginInvoke((Action)delegate{Place();});
  if(m.Msg==0x7E&&!closing){ReleaseApp(true);BeginInvoke((Action)delegate{Place();});}
  if(restartMessage!=0&&m.Msg==restartMessage&&!closing){registered=false;BeginInvoke((Action)delegate{RegisterBar();});}
  base.WndProc(ref m);
 }
 void Shutdown(){if(closing)return;closing=true;if(hook!=IntPtr.Zero)ShellNative.UnhookWinEvent(hook);StopWebcam();maintenance.Stop();ReleaseApp(true);if(broadcast!=null&&!broadcast.IsDisposed)broadcast.Close();if(registered){var d=Data();ShellNative.SHAppBarMessage(1,ref d);registered=false;}cameraTimer.Dispose();maintenance.Dispose();settle.Dispose();tray.Dispose();}
}
static class VirtualDisplayProgram {
 [STAThread] static void Main(){
  bool first;using(var mutex=new System.Threading.Mutex(true,"Local\\DTTL.DesktopDock",out first)){
   if(!first){MessageBox.Show("Hãy thoát bản DTTL đang chạy trước.");return;}
   try{Native.SetProcessDpiAwarenessContext(new IntPtr(-4));}catch(EntryPointNotFoundException){try{Native.SetProcessDpiAwareness(2);}catch{}}
   Application.EnableVisualStyles();Application.SetCompatibleTextRenderingDefault(false);
   using(var dock=new VirtualDesktopDock()){
    Application.ThreadException+=delegate(object sender,System.Threading.ThreadExceptionEventArgs e){MessageBox.Show("Ứng dụng gặp lỗi: "+e.Exception.Message);dock.Close();};Application.Run(dock);
   }
  }
 }
}
