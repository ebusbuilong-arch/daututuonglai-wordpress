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
// A monitor viewer, not a resized application window. Input stays native on the selected monitor.
internal sealed class VirtualDesktopDock : Form {
 const uint Callback=0x8101; const int ExitHotkey=103;
 readonly Button options=new Button{Text="Tùy chọn ▾",Size=new Size(120,34),Location=new Point(4,4),FlatStyle=FlatStyle.Flat};
 readonly Button quit=new Button{Text="×",Size=new Size(34,34),FlatStyle=FlatStyle.Flat};
 readonly Timer frameTimer=new Timer{Interval=100};
 readonly string[] sources=new string[3]; readonly Bitmap[] frames=new Bitmap[3];
 readonly Dictionary<IntPtr,DockedWindow> moved=new Dictionary<IntPtr,DockedWindow>();
 readonly NotifyIcon tray=new NotifyIcon{Icon=SystemIcons.Application,Text="DTTL • Màn hình ảo 0.4"};
 BroadcastForm broadcast;
 string host; int count=1,percent=33,active=-1; bool registered,closing,placing,hotkey;
 Point returnCursor; uint restartMessage;
 internal VirtualDesktopDock():this(true){}
 internal VirtualDesktopDock(bool showHelp){
  Text="DTTL • Màn hình ảo 0.4 thử nghiệm";FormBorderStyle=FormBorderStyle.None;ShowInTaskbar=false;
  StartPosition=FormStartPosition.Manual;AutoScaleMode=AutoScaleMode.None;DoubleBuffered=true;
  BackColor=Color.FromArgb(18,28,31);Font=new Font("Segoe UI",9);
  host=Screen.PrimaryScreen.DeviceName;Controls.Add(options);Controls.Add(quit);
  options.Click+=delegate{Menu().Show(options,new Point(0,options.Height));};quit.Click+=delegate{Close();};
  Shown+=delegate{
   restartMessage=ShellNative.RegisterWindowMessage("TaskbarCreated");RegisterBar();
   hotkey=Native.RegisterHotKey(Handle,ExitHotkey,0x4003,(uint)Keys.Space);
   tray.Visible=true;tray.MouseClick+=delegate{ReleaseInput();Menu().Show(Cursor.Position);};
   frameTimer.Start();
   if(!hotkey)MessageBox.Show(this,"Ctrl+Alt+Space đang được ứng dụng khác sử dụng. Chế độ điều khiển chưa bật; anh vẫn có thể xem màn hình.",Text);
   if(showHelp&&OtherScreens().Count==0)BeginInvoke((Action)delegate{SetupHelp();});
  };
  frameTimer.Tick+=delegate{CaptureFrames();};
  MouseDown+=delegate(object sender,MouseEventArgs e){if(e.Button==MouseButtons.Left)EnterInput(e.Location);};
  FormClosed+=delegate{Shutdown();};
 }
 Screen Find(string id){foreach(var s in Screen.AllScreens)if(s.DeviceName==id)return s;return null;}
 List<Screen> OtherScreens(){var list=new List<Screen>();foreach(var s in Screen.AllScreens)if(s.DeviceName!=host)list.Add(s);return list;}
 Rectangle Cell(int i){int h=Math.Max(1,ClientSize.Height-42);return Rectangle.FromLTRB(2,42+h*i/count+2,ClientSize.Width-2,42+h*(i+1)/count-2);}
 ShellNative.AppBar Data(){return new ShellNative.AppBar{Size=(uint)Marshal.SizeOf(typeof(ShellNative.AppBar)),Window=Handle,Callback=Callback,Edge=2};}
 void RegisterBar(){if(closing||registered)return;var d=Data();registered=ShellNative.SHAppBarMessage(0,ref d)!=UIntPtr.Zero;if(registered)Place();else{MessageBox.Show("Không giữ được vùng hiển thị phụ.");Close();}}
 void Place(){
  if(!registered||closing||placing)return;placing=true;
  try{
   var monitor=Find(host);if(monitor==null){ReleaseInput();host=Screen.PrimaryScreen.DeviceName;monitor=Screen.PrimaryScreen;}
   var b=monitor.Bounds;int w=Math.Max(240,b.Width*percent/100);var d=Data();d.Rect=new Native.Rect{L=b.Right-w,T=b.Top,R=b.Right,B=b.Bottom};
   ShellNative.SHAppBarMessage(2,ref d);d.Rect.L=d.Rect.R-w;ShellNative.SHAppBarMessage(3,ref d);
   SetBounds(d.Rect.L,d.Rect.T,d.Rect.R-d.Rect.L,d.Rect.B-d.Rect.T);quit.Location=new Point(ClientSize.Width-38,4);
   Native.SetWindowPos(Handle,new IntPtr(-1),0,0,0,0,0x13);
  }finally{placing=false;}
 }
 void Retire(ContextMenuStrip menu){menu.Closed+=delegate{if(!closing&&IsHandleCreated)BeginInvoke((Action)delegate{menu.Dispose();});};}
 ContextMenuStrip Menu(){
  ReleaseInput();var m=new ContextMenuStrip();
  for(int n=1;n<=3;n++){int v=n;var item=new ToolStripMenuItem(v+" ô"){Checked=count==v};item.Click+=delegate{count=v;Invalidate();};m.Items.Add(item);}
  var widths=new ToolStripMenuItem("Chiều rộng");foreach(int value in new[]{25,33,40,45}){int v=value;var item=new ToolStripMenuItem(v+"%"){Checked=percent==v};item.Click+=delegate{percent=v;Place();};widths.DropDownItems.Add(item);}m.Items.Add(widths);
  for(int i=0;i<count;i++){
   int index=i;var slot=new ToolStripMenuItem("Ô "+(i+1));var choose=new ToolStripMenuItem("Nguồn màn hình");
   foreach(var screen in OtherScreens()){
    string id=screen.DeviceName;var item=new ToolStripMenuItem(id+" • "+screen.Bounds.Width+"×"+screen.Bounds.Height){Checked=sources[index]==id};
    item.Click+=delegate{sources[index]=id;ResetFrame(index);};choose.DropDownItems.Add(item);
   }
   if(choose.DropDownItems.Count==0)choose.DropDownItems.Add("Chưa có màn hình phụ").Enabled=false;
   slot.DropDownItems.Add(choose);
   var move=slot.DropDownItems.Add("Đưa ứng dụng sang…",null,delegate{BeginInvoke((Action)delegate{if(!closing)PickWindow(index);});});move.Enabled=Find(sources[index])!=null;
   slot.DropDownItems.Add("Bỏ nguồn",null,delegate{sources[index]=null;ResetFrame(index);});m.Items.Add(slot);
  }
  m.Items.Add("Quay / Livestream…",null,delegate{BeginInvoke((Action)delegate{if(closing)return;if(broadcast==null||broadcast.IsDisposed)broadcast=new BroadcastForm();broadcast.Show();broadcast.Activate();});});
  m.Items.Add(new ToolStripSeparator());m.Items.Add("Trả ứng dụng về vị trí cũ",null,delegate{RestoreWindows();});
  m.Items.Add("Tải driver màn hình ảo",null,delegate{System.Diagnostics.Process.Start(new System.Diagnostics.ProcessStartInfo("https://github.com/VirtualDrivers/Virtual-Display-Driver/releases/tag/25.7.23"){UseShellExecute=true});});
  m.Items.Add("Cài màn hình ảo / Hướng dẫn",null,delegate{BeginInvoke((Action)delegate{if(!closing)SetupHelp();});});
  m.Items.Add("Thoát",null,delegate{BeginInvoke((Action)delegate{Close();});});Retire(m);return m;
 }
 void SetupHelp(){
  MessageBox.Show(this,"BẢN THỬ NGHIỆM MÀN HÌNH ẢO\n\n1. Cần một màn hình mở rộng thật hoặc driver màn hình ảo. Mỗi ô có thể chọn một màn hình riêng.\n2. Windows Settings → Display: chọn Extend, giữ màn hình vật lý là màn hình chính.\n3. Tùy chọn → Ô 1 → Nguồn màn hình; sau đó Đưa ứng dụng sang.\n4. Bấm trong hình để chuyển chuột/bàn phím sang màn hình đó. Bấm Ctrl+Alt+Space để trở về và mở Tùy chọn hoặc ×.\n\nChuột và bàn phím thao tác trực tiếp trên màn hình nguồn; đây không phải ảnh xem trước chỉ đọc. Thu/phóng ứng dụng diễn ra trên màn hình nguồn.\n\nNguồn driver: github.com/VirtualDrivers/Virtual-Display-Driver/releases/tag/25.7.23\nChỉ cài phần Virtual Display Driver bằng bộ cài của dự án. Ứng dụng này không tự cài driver hay thay đổi bảo vệ Windows.\n\nBản thử dùng chụp màn hình khoảng 10 lần/giây, dành cho làm việc văn phòng; chưa tối ưu game/video. Chỉ truyền mạng khi anh bật Livestream trong Tùy chọn → Quay / Livestream.\n\nGiữ Ctrl+Alt+Space để thoát điều khiển nếu chưa quen. Driver vẫn còn sau khi đóng app; gỡ bằng công cụ của nhà cung cấp.",Text);
 }
 void PickWindow(int index){
  var screen=Find(sources[index]);if(screen==null)return;var menu=new ContextMenuStrip();
  Native.EnumWindows(delegate(IntPtr h,IntPtr unused){var w=DesktopDock.Capture(h);if(!DesktopDock.Valid(w))return true;
   int cloaked;if(Native.Cloaked(h,14,out cloaked,4)==0&&cloaked!=0)return true;
   var title=new System.Text.StringBuilder(256);Native.GetWindowText(h,title,title.Capacity);
   menu.Items.Add(title.ToString(),null,delegate{
    if(!moved.ContainsKey(h))moved[h]=w;
    Native.ShowWindowAsync(h,9);var timer=new Timer{Interval=200};timer.Tick+=delegate{timer.Stop();timer.Dispose();if(closing||!DesktopDock.Valid(w))return;
     var current=Find(sources[index]);if(current==null)return;var r=current.WorkingArea;
     Native.SetWindowPos(h,IntPtr.Zero,r.Left+10,r.Top+10,Math.Max(300,r.Width-20),Math.Max(200,r.Height-20),0x4014);
     Native.ShowWindowAsync(h,3);
    };timer.Start();
   });return true;},IntPtr.Zero);
  if(menu.Items.Count==0)menu.Items.Add("Mở ứng dụng trước").Enabled=false;Retire(menu);menu.Show(this,new Point(4,40));
 }
 void ResetFrame(int i){if(frames[i]!=null){frames[i].Dispose();frames[i]=null;}Invalidate();}
 void CaptureFrames(){
  if(closing)return;
  for(int i=0;i<count;i++){
   var s=Find(sources[i]);if(s==null||s.DeviceName==host){if(active==i)ReleaseInput();ResetFrame(i);continue;}
   var r=s.Bounds;
   // Never capture the host screen (recursive display). Large displays have an explicit cap.
   if(r.Width>4096||r.Height>4096){if(active==i)ReleaseInput();ResetFrame(i);continue;}
   try{
    if(frames[i]==null||frames[i].Width!=r.Width||frames[i].Height!=r.Height){ResetFrame(i);frames[i]=new Bitmap(r.Width,r.Height);}
    using(var g=Graphics.FromImage(frames[i]))g.CopyFromScreen(r.Location,Point.Empty,r.Size,CopyPixelOperation.SourceCopy);
   }catch(System.ComponentModel.Win32Exception){if(active==i)ReleaseInput();ResetFrame(i);}catch(ExternalException){if(active==i)ReleaseInput();ResetFrame(i);}
  }
  Invalidate();
 }
 void EnterInput(Point point){
  if(!hotkey)return;
  for(int i=0;i<count;i++){
   var s=Find(sources[i]);if(s==null||frames[i]==null)continue;
   var view=ViewGeometry.Fit(Cell(i),s.Bounds.Size);if(!view.Contains(point))continue;
   returnCursor=Cursor.Position;var target=ViewGeometry.ToSource(point,view,s.Bounds);
   var clip=new Native.Rect{L=s.Bounds.Left,T=s.Bounds.Top,R=s.Bounds.Right,B=s.Bounds.Bottom};
   if(!VirtualNative.Clip(ref clip))return;
   active=i;VirtualNative.SetCursorPos(target.X,target.Y);
   var h=VirtualNative.GetAncestor(VirtualNative.WindowFromPoint(new Native.Point{X=target.X,Y=target.Y}),2);
   if(h!=IntPtr.Zero)VirtualNative.SetForegroundWindow(h);
   Invalidate();return;
  }
 }
 internal void ReleaseInput(){if(active<0)return;active=-1;VirtualNative.Unclip(IntPtr.Zero);VirtualNative.SetCursorPos(returnCursor.X,returnCursor.Y);Invalidate();}
 protected override void OnPaint(PaintEventArgs e){
  base.OnPaint(e);e.Graphics.InterpolationMode=InterpolationMode.HighQualityBilinear;
  for(int i=0;i<count;i++){
   var cell=Cell(i);var s=Find(sources[i]);
   if(s!=null&&frames[i]!=null){var view=ViewGeometry.Fit(cell,frames[i].Size);e.Graphics.DrawImage(frames[i],view);
    if(active==i){var pt=ViewGeometry.ToView(Cursor.Position,s.Bounds,view);var p=new[]{pt,new Point(pt.X+3,pt.Y+17),new Point(pt.X+7,pt.Y+11),new Point(pt.X+14,pt.Y+10)};e.Graphics.FillPolygon(Brushes.White,p);e.Graphics.DrawPolygon(Pens.Black,p);}
   }
   using(var pen=new Pen(active==i?Color.LimeGreen:Color.FromArgb(70,100,95),active==i?3:1))e.Graphics.DrawRectangle(pen,cell);
  }
  if(active>=0)TextRenderer.DrawText(e.Graphics,"Ctrl+Alt+Space: trở về",Font,new Rectangle(135,7,Math.Max(1,Width-175),30),Color.White,TextFormatFlags.VerticalCenter|TextFormatFlags.EndEllipsis);
 }
 void RestoreWindows(){ReleaseInput();foreach(var w in moved.Values)if(DesktopDock.Valid(w)){var p=w.Original;Native.SetWindowPlacement(w.Handle,ref p);}moved.Clear();}
 protected override void WndProc(ref Message m){
  if(m.Msg==0x312&&m.WParam.ToInt32()==ExitHotkey){ReleaseInput();Activate();}
  if(m.Msg==Callback&&m.WParam.ToInt32()==1&&!placing&&!closing)BeginInvoke((Action)delegate{Place();});
  if(m.Msg==0x7E&&!closing){ReleaseInput();RestoreWindows();BeginInvoke((Action)delegate{Place();});}
  if(restartMessage!=0&&m.Msg==restartMessage&&!closing){registered=false;BeginInvoke((Action)delegate{RegisterBar();});}
  if(m.Msg==0x11)ReleaseInput();
  base.WndProc(ref m);
 }
 void Shutdown(){if(closing)return;ReleaseInput();closing=true;if(broadcast!=null&&!broadcast.IsDisposed)broadcast.Close();frameTimer.Stop();RestoreWindows();if(hotkey)Native.UnregisterHotKey(Handle,ExitHotkey);if(registered){var d=Data();ShellNative.SHAppBarMessage(1,ref d);registered=false;}for(int i=0;i<3;i++)ResetFrame(i);frameTimer.Dispose();tray.Dispose();}
}
static class VirtualDisplayProgram {
 [STAThread] static void Main(){
  bool first;using(var mutex=new System.Threading.Mutex(true,"Local\\DTTL.DesktopDock",out first)){
   if(!first){MessageBox.Show("Hãy thoát bản DTTL đang chạy trước.");return;}
   try{Native.SetProcessDpiAwarenessContext(new IntPtr(-4));}catch(EntryPointNotFoundException){try{Native.SetProcessDpiAwareness(2);}catch{}}
   Application.EnableVisualStyles();Application.SetCompatibleTextRenderingDefault(false);
   using(var dock=new VirtualDesktopDock()){
    Application.ThreadException+=delegate(object sender,System.Threading.ThreadExceptionEventArgs e){dock.ReleaseInput();MessageBox.Show("Đã trả chuột về màn hình chính. Ứng dụng gặp lỗi: "+e.Exception.Message);dock.Close();};
    try{Application.Run(dock);}finally{dock.ReleaseInput();}
   }
  }
 }
}
