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
 readonly Button webcamButton=new Button{Text="Bật webcam",Size=new Size(115,28),Location=new Point(136,7),FlatStyle=FlatStyle.Flat};
 readonly Timer cameraTimer=new Timer{Interval=66},settle=new Timer{Interval=250},maintenance=new Timer{Interval=750};
 readonly NotifyIcon tray=new NotifyIcon{Icon=SystemIcons.Application,Text="DTTL • Không gian làm việc 0.6.4"};
 readonly ShellNative.WinEvent moveEvent;
 IntPtr hook,moving;DockedWindow app,beforeMove;NativeWebcam webcam;Bitmap webcamImage;BroadcastForm broadcast;
 ScreenInk ink;
 string lastFit="Chưa ghim ứng dụng trong phiên này.";
 string webcamStatus="Webcam đang tắt",appStatus="Tùy chọn → Chọn Zalo / Telegram / Chrome";
 string host;bool registered,closing,placing,cameraVisible,cameraOpening,dragWidth,dragSplit;int cameraGeneration,fitGeneration,fitAttempts;double sidebarRatio=.33,splitRatio=1.0/3;
 readonly string settings=Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),"DTTL","Workspace.ini");
uint restartMessage;
 internal VirtualDesktopDock():this(true){}
 internal VirtualDesktopDock(bool readSettings){
  Text="DTTL • Không gian làm việc 0.6.4";FormBorderStyle=FormBorderStyle.None;ShowInTaskbar=false;StartPosition=FormStartPosition.Manual;AutoScaleMode=AutoScaleMode.None;DoubleBuffered=true;
  BackColor=Color.FromArgb(20,31,37);ForeColor=Color.FromArgb(235,245,247);Font=new Font("Segoe UI",9);
  foreach(var button in new[]{options,quit,webcamButton}){button.TextAlign=ContentAlignment.MiddleCenter;button.Padding=Padding.Empty;button.ForeColor=Color.White;button.BackColor=Color.FromArgb(40,65,73);button.FlatAppearance.BorderColor=Color.FromArgb(76,116,124);button.FlatAppearance.MouseOverBackColor=Color.FromArgb(55,93,102);button.FlatAppearance.MouseDownBackColor=Color.FromArgb(35,120,123);Controls.Add(button);}
  options.Click+=delegate{Menu().Show(options,new Point(0,options.Height));};quit.Click+=delegate{Close();};webcamButton.Click+=delegate{ToggleWebcam();};
  host=Screen.PrimaryScreen.DeviceName;if(readSettings)LoadSettings();
  moveEvent=delegate(IntPtr h,uint ev,IntPtr window,int obj,int child,uint thread,uint time){
   if(closing||obj!=0||!IsHandleCreated)return;
   if(ev==0xA){
    var captured=DesktopDock.Capture(window);if(!DesktopDock.Valid(captured))return;
    beforeMove=app!=null&&app.Handle==window?app:captured;moving=window;fitGeneration++;settle.Stop();
    // Do not place or raise either dock surface over a window while the user drags it.
    Native.SetWindowPos(Handle,new IntPtr(-2),0,0,0,0,0x13);
    Native.SetWindowPos(Handle,window,0,0,0,0,0x13);
    if(DesktopDock.Valid(app)&&app.Handle!=window){Native.SetWindowPos(app.Handle,new IntPtr(-2),0,0,0,0,0x13);Native.SetWindowPos(app.Handle,window,0,0,0,0,0x13);}
    return;
   }
   if(ev!=0xB)return;
   var saved=beforeMove!=null&&beforeMove.Handle==window?beforeMove:DesktopDock.Capture(window);beforeMove=null;
   Native.Point drop;bool hasDrop=ShellNative.GetCursorPos(out drop);moving=IntPtr.Zero;
   BeginInvoke((Action)delegate{if(closing)return;Native.SetWindowPos(Handle,new IntPtr(-1),0,0,0,0,0x13);
    if(hasDrop){var target=ApplicationArea;target.Inflate(28,0);
     if(target.Contains(drop.X,drop.Y)){if(DesktopDock.Valid(saved))Attach(saved);}
     else if(app!=null&&app.Handle==window)ReleaseApp(false);
    }
    RaiseApp();
   });
  };
  Shown+=delegate{restartMessage=ShellNative.RegisterWindowMessage("TaskbarCreated");RegisterBar();ink=new ScreenInk();ink.Show(MainArea());hook=ShellNative.SetWinEventHook(0xA,0xB,IntPtr.Zero,moveEvent,0,0,2);tray.Visible=true;tray.MouseClick+=delegate{Menu().Show(Cursor.Position);};maintenance.Start();};
  Activated+=delegate{if(!closing&&DesktopDock.Valid(app))BeginInvoke((Action)delegate{RaiseApp();});};
  settle.Tick+=delegate{settle.Stop();FitApp();};
  maintenance.Tick+=delegate{if(moving!=IntPtr.Zero&&!DesktopDock.Valid(beforeMove)){moving=IntPtr.Zero;beforeMove=null;Native.SetWindowPos(Handle,new IntPtr(-1),0,0,0,0,0x13);}if(app!=null&&!DesktopDock.Valid(app)){app=null;Invalidate();}RaiseApp();};
  cameraTimer.Tick+=delegate{if(webcam==null)return;var next=webcam.Take();if(next!=null){if(webcamImage!=null)webcamImage.Dispose();webcamImage=next;Invalidate(Cell(0));}if(webcam.Ended){string error=webcam.Error;StopWebcam();tray.ShowBalloonTip(5000,"Webcam",error??"Webcam đã dừng. Kiểm tra kết nối/quyền Camera.",ToolTipIcon.Info);}};
  FormClosed+=delegate{Shutdown();};
 }
 internal static Rectangle Pane(Rectangle client,int index){return Pane(client,index,true,1.0/3);}
 internal static Rectangle Pane(Rectangle client,int index,bool camera,double ratio){
  int top=client.Top+42,bottom=client.Bottom-8;
  if(!camera)return index==0?Rectangle.Empty:Rectangle.FromLTRB(client.Left+16,top+5,client.Right-8,bottom);
  int split=top+(int)(Math.Max(1,client.Height-42)*Math.Max(.06,Math.Min(.65,ratio)));
  return index==0?Rectangle.FromLTRB(client.Left+16,top+5,client.Right-8,split-5):Rectangle.FromLTRB(client.Left+16,split+5,client.Right-8,bottom);
 }
 Rectangle Cell(int index){return Pane(ClientRectangle,index,cameraVisible,splitRatio);}
 internal Rectangle ApplicationArea{get{var r=Cell(1);r.Offset(Left,Top);return r;}}
 Rectangle WebcamArea(){return Cell(0);}
 internal void SetLayout(double width,double split,bool camera){sidebarRatio=Math.Max(.25,Math.Min(.65,width));splitRatio=Math.Max(.06,Math.Min(.65,split));cameraVisible=camera;fitAttempts=0;fitGeneration++;Place();Invalidate();}
 void LoadSettings(){try{var v=File.ReadAllLines(settings);double w,h;if(v.Length>=2&&double.TryParse(v[0],System.Globalization.NumberStyles.Float,System.Globalization.CultureInfo.InvariantCulture,out w)&&double.TryParse(v[1],System.Globalization.NumberStyles.Float,System.Globalization.CultureInfo.InvariantCulture,out h)){sidebarRatio=Math.Max(.25,Math.Min(.65,w));splitRatio=Math.Max(.06,Math.Min(.65,h));}}catch(IOException){}catch(UnauthorizedAccessException){}}
 void SaveSettings(){try{Directory.CreateDirectory(Path.GetDirectoryName(settings));File.WriteAllLines(settings,new[]{sidebarRatio.ToString(System.Globalization.CultureInfo.InvariantCulture),splitRatio.ToString(System.Globalization.CultureInfo.InvariantCulture)});}catch(IOException){}catch(UnauthorizedAccessException){}}
 protected override void OnMouseDown(MouseEventArgs e){base.OnMouseDown(e);if(e.Button!=MouseButtons.Left)return;dragWidth=e.X<=8;dragSplit=!dragWidth&&cameraVisible&&Math.Abs(e.Y-(42+(int)((ClientSize.Height-42)*splitRatio)))<=6;if(dragWidth||dragSplit)Capture=true;}
 protected override void OnMouseMove(MouseEventArgs e){base.OnMouseMove(e);
  if(dragWidth){var screen=Screen.FromHandle(Handle);SetLayout((double)(screen.Bounds.Right-Cursor.Position.X)/screen.Bounds.Width,splitRatio,cameraVisible);}
  else if(dragSplit){SetLayout(sidebarRatio,(double)(e.Y-42)/Math.Max(1,ClientSize.Height-42),true);}
  else Cursor=e.X<=8?Cursors.SizeWE:cameraVisible&&Math.Abs(e.Y-(42+(int)((ClientSize.Height-42)*splitRatio)))<=6?Cursors.SizeNS:Cursors.Default;
 }
 protected override void OnMouseUp(MouseEventArgs e){base.OnMouseUp(e);if(dragWidth||dragSplit){dragWidth=dragSplit=false;Capture=false;SaveSettings();settle.Stop();settle.Start();}}
 ShellNative.AppBar Data(){return new ShellNative.AppBar{Size=(uint)Marshal.SizeOf(typeof(ShellNative.AppBar)),Window=Handle,Callback=Callback,Edge=2};}
 void RegisterBar(){if(closing||registered)return;var d=Data();registered=ShellNative.SHAppBarMessage(0,ref d)!=UIntPtr.Zero;if(registered)Place();else{MessageBox.Show("Không giữ được vùng phụ.");Close();}}
 void Place(){
  if(!registered||closing||placing)return;placing=true;
  try{Screen monitor=null;foreach(var s in Screen.AllScreens)if(s.DeviceName==host)monitor=s;if(monitor==null){ReleaseApp(true);monitor=Screen.PrimaryScreen;host=monitor.DeviceName;}
   var b=monitor.Bounds;int width=(int)(b.Width*sidebarRatio);var d=Data();d.Rect=new Native.Rect{L=b.Right-width,T=b.Top,R=b.Right,B=b.Bottom};ShellNative.SHAppBarMessage(2,ref d);d.Rect.L=d.Rect.R-width;ShellNative.SHAppBarMessage(3,ref d);
   SetBounds(d.Rect.L,d.Rect.T,d.Rect.R-d.Rect.L,d.Rect.B-d.Rect.T);quit.Location=new Point(ClientSize.Width-40,5);if(moving==IntPtr.Zero)Native.SetWindowPos(Handle,new IntPtr(-1),0,0,0,0,0x13);
   if(moving==IntPtr.Zero&&DesktopDock.Valid(app)){settle.Stop();settle.Start();}if(ink!=null)ink.SetArea(MainArea());Invalidate();
  }finally{placing=false;}
 }
 Rectangle MainArea(){var b=Screen.FromHandle(Handle).Bounds;return Rectangle.FromLTRB(b.Left,Top,Left,Bottom);}
 void Retire(ContextMenuStrip menu){menu.Closed+=delegate{if(!closing&&IsHandleCreated)BeginInvoke((Action)delegate{menu.Dispose();});};}
 ContextMenuStrip Menu(){
  var m=new ContextMenuStrip{BackColor=Color.White,ForeColor=Color.FromArgb(20,30,35)};
  m.Items.Add("Mặc định rộng 33%",null,delegate{SetLayout(.33,1.0/3,cameraVisible);SaveSettings();});
  m.Items.Add("Chọn Zalo / Telegram / Chrome…",null,delegate{BeginInvoke((Action)delegate{if(!closing)PickWindow();});});
  var show=m.Items.Add("Căn lại / Hiện ứng dụng",null,delegate{if(DesktopDock.Valid(app)){Native.ShowWindowAsync(app.Handle,9);settle.Start();}});show.Enabled=DesktopDock.Valid(app);
  var release=m.Items.Add("Trả ứng dụng về vị trí cũ",null,delegate{ReleaseApp(true);});release.Enabled=DesktopDock.Valid(app);
  m.Items.Add(new ToolStripSeparator());
  m.Items.Add("Bút vẽ / Hiệu ứng",null,delegate{if(ink!=null)ink.Show(MainArea());});
  m.Items.Add("Quay / Livestream…",null,delegate{BeginInvoke((Action)delegate{if(closing)return;if(broadcast==null||broadcast.IsDisposed)broadcast=new BroadcastForm();if(!broadcast.Visible)broadcast.Show(this);broadcast.Activate();});});
  m.Items.Add("Hướng dẫn",null,delegate{BeginInvoke((Action)delegate{if(!closing)MessageBox.Show(this,"Không cần driver màn hình ảo.\n\nVùng phụ mặc định rộng 33%. Kéo mép trái để đổi chiều rộng. Tắt webcam: ứng dụng dùng hết chiều cao. Bật webcam: kéo vách ngăn ngang để chia lại hai ô.\nTùy chọn → Chọn Zalo / Telegram / Chrome, hoặc kéo thanh tiêu đề cửa sổ vào ô dưới. Chat, cuộn và gõ trực tiếp. Kéo ra ngoài để bỏ ghim.\n\nBật webcam tự nhận camera Windows, không cần FFmpeg. Cho phép ứng dụng desktop truy cập camera trong Windows Settings. Webcam chỉ xem tại máy cho đến khi anh chủ động quay/phát màn hình chứa nó.\n\nNếu ứng dụng không cho thu nhỏ đủ, app sẽ trả lại vị trí cũ và báo rõ. Kích thước vật lý 32 inch không quyết định số điểm ảnh; độ phân giải và Scale cũng ảnh hưởng.\n\nĐóng DTTL sẽ trả cửa sổ và vùng desktop. Driver đã cài trước đó không tự gỡ.",Text);});});
  m.Items.Add("Thông tin lần ghim gần nhất…",null,delegate{BeginInvoke((Action)delegate{if(!closing)MessageBox.Show(this,lastFit,"Thông tin ghim cửa sổ — Ctrl+C để sao chép");});});
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
  fitAttempts=0;fitGeneration++;appStatus="";Native.ShowWindowAsync(app.Handle,9);settle.Stop();settle.Start();Invalidate();
 }
 void RaiseApp(){if(moving!=IntPtr.Zero||dragWidth||dragSplit)return;foreach(Form owned in OwnedForms)if(owned.Visible)return;if(!closing&&DesktopDock.Valid(app)&&!ShellNative.IsIconic(app.Handle))Native.SetWindowPos(app.Handle,new IntPtr(-1),0,0,0,0,0x13);}
 internal static bool FitsInside(Native.Rect actual,Rectangle wanted){return actual.R-actual.L>80&&actual.B-actual.T>80&&actual.L>=wanted.Left-12&&actual.T>=wanted.Top-12&&actual.R<=wanted.Right+12&&actual.B<=wanted.Bottom+12;}
 static string BoundsText(Native.Rect r){return r.L+","+r.T+" → "+r.R+","+r.B+" ("+(r.R-r.L)+"×"+(r.B-r.T)+")";}
 void FitApp(){
  if(closing||moving!=IntPtr.Zero||dragWidth||dragSplit||!DesktopDock.Valid(app))return;
  var wanted=ApplicationArea;Native.Rect outer,visible;int l=0,t=0,r=0,b=0;
  bool haveOuter=Native.GetWindowRect(app.Handle,out outer);
  int dwm=Native.DwmGetWindowAttribute(app.Handle,9,out visible,Marshal.SizeOf(typeof(Native.Rect)));
  if(haveOuter&&dwm==0&&visible.R>visible.L&&visible.B>visible.T){l=Math.Max(0,Math.Min(32,visible.L-outer.L));t=Math.Max(0,Math.Min(32,visible.T-outer.T));r=Math.Max(0,Math.Min(32,outer.R-visible.R));b=Math.Max(0,Math.Min(32,outer.B-visible.B));}
  lastFit="DTTL 0.6.4 • "+DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss")+"\nÔ đích: "+wanted+"\nTrước ghim (Win32): "+BoundsText(outer)+"\nTrước ghim (DWM): "+BoundsText(visible)+"\nDWM result: "+dwm+"; bù viền: "+l+","+t+","+r+","+b;
  bool ok=Native.SetWindowPos(app.Handle,new IntPtr(-1),wanted.X-l,wanted.Y-t,wanted.Width+l+r,wanted.Height+t+b,0x4010);
  int winError=ok?0:Marshal.GetLastWin32Error();lastFit+="\nSetWindowPos: "+ok+"; Windows error: "+winError;
  int generation=++fitGeneration;var expected=app;int polls=0;Native.Rect previous=new Native.Rect();int stable=0;
  var verify=new Timer{Interval=200};
  Action finish=delegate{verify.Stop();verify.Dispose();};
  verify.Tick+=delegate{
   if(closing||generation!=fitGeneration||app!=expected||!DesktopDock.Valid(expected)){finish();return;}
   Native.Rect actual;int result=Native.DwmGetWindowAttribute(expected.Handle,9,out actual,Marshal.SizeOf(typeof(Native.Rect)));
   if(result!=0||actual.R<=actual.L||actual.B<=actual.T)Native.GetWindowRect(expected.Handle,out actual);
   polls++;stable=actual.L==previous.L&&actual.T==previous.T&&actual.R==previous.R&&actual.B==previous.B?stable+1:0;previous=actual;
   lastFit+="\n"+(polls*200)+" ms: "+BoundsText(actual);
   if(ok&&FitsInside(actual,wanted)){if(stable<1)return;lastFit+="\nKết quả: cửa sổ nằm trong ô (không bắt buộc lấp đầy ô).";finish();return;}
   // An async request can take longer than 200 ms. Expand only after the window has moved to the destination.
   bool atTarget=Math.Abs(actual.L-wanted.Left)<=12&&Math.Abs(actual.T-wanted.Top)<=12;
   if(ok&&atTarget&&stable>=2&&fitAttempts<2){var screen=Screen.FromHandle(Handle);int aw=actual.R-actual.L,ah=actual.B-actual.T;bool changed=false;
    if(aw>wanted.Width+12){double width=Math.Min(.65,(double)(aw+28)/screen.Bounds.Width);if(width>sidebarRatio+.001){sidebarRatio=width;changed=true;}}
    if(cameraVisible&&ah>wanted.Height+12){double split=Math.Max(.06,(double)(ClientSize.Height-42-ah-17)/Math.Max(1,ClientSize.Height-42));if(split<splitRatio-.001){splitRatio=split;changed=true;}}
    if(changed){fitAttempts++;finish();Place();settle.Stop();settle.Start();SaveSettings();return;}
   }
   if(ok&&polls<10)return;
   finish();string reason=!ok?(winError==5?"Windows từ chối quyền điều khiển cửa sổ.":"Windows không nhận lệnh đặt cửa sổ (mã "+winError+")."):!atTarget?"Ứng dụng chưa chuyển đến vị trí yêu cầu.":"Kích thước thực tế của ứng dụng vượt ô đích.";
   lastFit+="\nKết quả: "+reason+"\nĐã trả về vị trí cũ.";
   ReleaseApp(true);appStatus=reason+"\nTùy chọn → Thông tin lần ghim gần nhất.";tray.ShowBalloonTip(6000,"Chưa ghim được ứng dụng",reason+" Xem thông tin lần ghim trong Tùy chọn.",ToolTipIcon.Info);Invalidate();
  };verify.Start();
 }
 internal void ReleaseApp(bool restore){fitGeneration++;settle.Stop();var old=app;app=null;if(DesktopDock.Valid(old)){Native.SetWindowPos(old.Handle,new IntPtr(old.WasTopMost?-1:-2),0,0,0,0,0x13);if(restore){var p=old.Original;Native.SetWindowPlacement(old.Handle,ref p);}}appStatus="Tùy chọn → Chọn Zalo / Telegram / Chrome";Invalidate();}
 void StopWebcam(){cameraGeneration++;cameraOpening=false;cameraTimer.Stop();if(webcam!=null){webcam.Dispose();webcam=null;}if(webcamImage!=null){webcamImage.Dispose();webcamImage=null;}webcamButton.Text="Bật webcam";SetLayout(sidebarRatio,splitRatio,false);}
 void ToggleWebcam(){
  if(webcam!=null||cameraOpening){StopWebcam();return;}
  cameraOpening=true;int generation=++cameraGeneration;webcamButton.Text="Hủy mở camera";webcamStatus="Đang tìm camera…";SetLayout(sidebarRatio,splitRatio,true);
  System.Threading.ThreadPool.QueueUserWorkItem(delegate{
   NativeWebcam feed=null;string error=null;try{feed=NativeWebcam.OpenDefault();}catch(Exception e){error=e.Message;}
   if(closing||!IsHandleCreated){if(feed!=null)feed.Dispose();return;}
   try{BeginInvoke((Action)delegate{if(closing||generation!=cameraGeneration){if(feed!=null)feed.Dispose();return;}cameraOpening=false;
    if(feed==null){StopWebcam();tray.ShowBalloonTip(6000,"Không mở được webcam",error??"Kiểm tra kết nối và quyền Camera của Windows.",ToolTipIcon.Info);return;}
    webcam=feed;webcamButton.Text="Tắt webcam";webcamStatus="Đang mở webcam…";cameraTimer.Start();Invalidate();
   });}catch(InvalidOperationException){if(feed!=null)feed.Dispose();}
  });
 }
 protected override void OnPaint(PaintEventArgs e){
  base.OnPaint(e);var upper=Cell(0);var lower=Cell(1);
  using(var fill=new SolidBrush(Color.FromArgb(25,41,47))){e.Graphics.FillRectangle(fill,upper);e.Graphics.FillRectangle(fill,lower);}
  using(var border=new Pen(Color.FromArgb(54,86,93))){if(cameraVisible)e.Graphics.DrawRectangle(border,upper);e.Graphics.DrawRectangle(border,lower);}
  int split=42+(int)((ClientSize.Height-42)*splitRatio);
  using(var line=new Pen(Color.FromArgb(59,139,143))){if(cameraVisible)e.Graphics.DrawLine(line,14,split,Width-14,split);e.Graphics.DrawLine(line,1,6,1,Height-6);}
  using(var grip=new Pen(Color.FromArgb(116,212,203),3)){if(cameraVisible)e.Graphics.DrawLine(grip,Width/2-15,split,Width/2+15,split);}
  if(webcamImage!=null){e.Graphics.InterpolationMode=InterpolationMode.Bilinear;e.Graphics.DrawImage(webcamImage,ViewGeometry.Fit(WebcamArea(),webcamImage.Size));}
  else if(cameraVisible)TextRenderer.DrawText(e.Graphics,webcamStatus,Font,WebcamArea(),ForeColor,TextFormatFlags.HorizontalCenter|TextFormatFlags.VerticalCenter|TextFormatFlags.WordBreak);
  if(!DesktopDock.Valid(app))TextRenderer.DrawText(e.Graphics,appStatus,Font,lower,ForeColor,TextFormatFlags.HorizontalCenter|TextFormatFlags.VerticalCenter|TextFormatFlags.WordBreak);
 }
 internal void ReleaseInput(){} // No cursor clipping or forwarding in the driver-free version.
 protected override void WndProc(ref Message m){
  if(m.Msg==Callback&&m.WParam.ToInt32()==1&&!placing&&!closing)BeginInvoke((Action)delegate{Place();});
  if(m.Msg==0x7E&&!closing){ReleaseApp(true);BeginInvoke((Action)delegate{Place();});}
  if(restartMessage!=0&&m.Msg==restartMessage&&!closing){registered=false;BeginInvoke((Action)delegate{RegisterBar();});}
  base.WndProc(ref m);
 }
 void Shutdown(){if(closing)return;SaveSettings();closing=true;if(ink!=null)ink.Dispose();if(hook!=IntPtr.Zero)ShellNative.UnhookWinEvent(hook);StopWebcam();maintenance.Stop();ReleaseApp(true);if(broadcast!=null&&!broadcast.IsDisposed)broadcast.Close();if(registered){var d=Data();ShellNative.SHAppBarMessage(1,ref d);registered=false;}cameraTimer.Dispose();maintenance.Dispose();settle.Dispose();tray.Dispose();}
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
