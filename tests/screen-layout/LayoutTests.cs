using System;
using System.Drawing;
using System.Diagnostics;
using System.Collections.Generic;
using System.Reflection;
using System.Runtime.InteropServices;
using System.Windows.Forms;
class LayoutTests {
 [DllImport("user32.dll")] static extern void mouse_event(uint flags,uint dx,uint dy,uint data,UIntPtr extra);
 [DllImport("user32.dll")] static extern bool SetForegroundWindow(IntPtr h);
 [DllImport("user32.dll")] static extern IntPtr WindowFromPoint(Native.Point p);
 [DllImport("user32.dll")] static extern IntPtr GetAncestor(IntPtr h,uint flag);
 [DllImport("user32.dll")] static extern bool SetCursorPos(int x,int y);
 [DllImport("user32.dll")] static extern void NotifyWinEvent(uint ev,IntPtr h,int obj,int child);
 static void Pump(int ms){var t=Stopwatch.StartNew();while(t.ElapsedMilliseconds<ms){Application.DoEvents();System.Threading.Thread.Sleep(10);}}
 static Native.Rect Read(IntPtr h){Native.Rect r;if(Native.DwmGetWindowAttribute(h,9,out r,Marshal.SizeOf(typeof(Native.Rect)))!=0)Native.GetWindowRect(h,out r);return r;}
 static void Near(IntPtr h,Rectangle wanted,string message){var a=Read(h);if(Math.Abs(a.L-wanted.Left)>5||Math.Abs(a.T-wanted.Top)>5||Math.Abs(a.R-wanted.Right)>5||Math.Abs(a.B-wanted.Bottom)>5)throw new Exception(message+" got="+a.L+","+a.T+","+a.R+","+a.B+" expected="+wanted);}
 static Rectangle Rect(IntPtr h){var r=Read(h);return Rectangle.FromLTRB(r.L,r.T,r.R,r.B);}
 static void RealDrag(IntPtr window,Point target){
  SetForegroundWindow(window);Pump(150);var r=Read(window);
  int x=r.L+100,y=r.T+12;SetCursorPos(x,y);Pump(80);
  mouse_event(2,0,0,0,UIntPtr.Zero);Pump(80);
  for(int i=1;i<=20;i++){SetCursorPos(x+(target.X-x)*i/20,y+(target.Y-y)*i/20);Pump(35);}
  mouse_event(4,0,0,0,UIntPtr.Zero);Pump(1000);
 }
 static void Fixture(string index){
  var f=new Form{Text="DTTL test window "+index,StartPosition=FormStartPosition.Manual,Location=new Point(40+int.Parse(index)*20,60),Size=new Size(340,250)};
  f.Shown+=delegate{Console.WriteLine(f.Handle.ToInt64());Console.Out.Flush();};
  var thread=new System.Threading.Thread(delegate(){string line;while((line=Console.ReadLine())!=null){var parts=line.Split(' ');f.BeginInvoke((Action)delegate{if(parts[0]=="drag"){NotifyWinEvent(0xA,f.Handle,0,0);SetCursorPos(int.Parse(parts[1]),int.Parse(parts[2]));NotifyWinEvent(0xB,f.Handle,0,0);} });}});thread.IsBackground=true;thread.Start();Application.Run(f);
 }
 [STAThread] public static void Main(string[] args){
  try{Native.SetProcessDpiAwarenessContext(new IntPtr(-4));}catch{}
  Application.EnableVisualStyles();if(args.Length>0&&args[0]=="fixture"){Fixture(args[1]);return;}
  int cases=0;foreach(var area in new[]{new Rectangle(900,0,466,728),new Rectangle(-600,0,600,1040),new Rectangle(2700,-2160,1140,2120)})for(int count=1;count<=3;count++)for(int i=0;i<count;i++){
   var c=DockGeometry.Cell(area,count,i);if(!area.Contains(c)||c.Width<=0||c.Height<=0)throw new Exception("Invalid cell");if(DockGeometry.Hit(area,count,new Point(c.Left+10,c.Top+10))!=i)throw new Exception("Hit failed");for(int j=0;j<i;j++)if(c.IntersectsWith(DockGeometry.Cell(area,count,j)))throw new Exception("Overlap");cases++;
  }
  var edge=new Rectangle(900,0,466,728);
  if(DockGeometry.Hit(edge,2,new Point(899,135))!=0||DockGeometry.Hit(edge,2,new Point(899,500))!=1||DockGeometry.Hit(edge,2,new Point(880,135))!=-1||DockGeometry.Hit(edge,2,new Point(920,60))!=-1)throw new Exception("Drop edge/header hit regression");
  Console.WriteLine("PASS: "+cases+" auxiliary cell geometry/hit cases including negative monitors");
  var processes=new List<Process>();var handles=new List<IntPtr>();var initial=new List<Rectangle>();var workBefore=Screen.PrimaryScreen.WorkingArea;
  try {
   for(int i=0;i<3;i++){var p=Process.Start(new ProcessStartInfo(Assembly.GetExecutingAssembly().Location,"fixture "+i){UseShellExecute=false,RedirectStandardOutput=true,RedirectStandardInput=true});processes.Add(p);handles.Add(new IntPtr(long.Parse(p.StandardOutput.ReadLine())));initial.Add(Rect(handles[i]));}
   using(var dock=new DesktopDock(false)){
    dock.Show();Pump(1000);if(!dock.Reserved)throw new Exception("AppBar not registered");
    var remaining=Screen.FromHandle(handles[0]).WorkingArea;
    if(remaining.Right>dock.Left+2||remaining.Width>=workBefore.Width)throw new Exception("Desktop work area not reserved");
    Native.ShowWindowAsync(handles[2],3);Pump(400);var main=Read(handles[2]);if(main.R>dock.Left+5)throw new Exception("Main maximized window covers sidebar");Native.ShowWindowAsync(handles[2],9);Pump(250);
    Console.WriteLine("PASS: native AppBar reserves desktop; maximized main window respects auxiliary area");
    for(int n=1;n<=3;n++){
     dock.Configure(n,40,Screen.PrimaryScreen.DeviceName);Pump(500);
     for(int i=0;i<n;i++){var c=dock.Cells[i];processes[i].StandardInput.WriteLine("drag "+(c.Left+35)+" "+(c.Top+35));processes[i].StandardInput.Flush();Pump(700);Near(handles[i],c,"Event-hook docking "+n+"/"+i);if(GetAncestor(WindowFromPoint(new Native.Point{X=c.Left+c.Width/2,Y=c.Top+c.Height/2}),2)!=handles[i])throw new Exception("Docked window obscured by sidebar");}
     dock.ReleaseAll();Pump(400);for(int i=0;i<n;i++)Near(handles[i],initial[i],"Restore original placement");
    }
    Console.WriteLine("PASS: external move events dock 1/2/3 windows and restore original placements");
    dock.Configure(2,40,Screen.PrimaryScreen.DeviceName);Pump(500);
    var realCell=dock.Cells[1];
    RealDrag(handles[0],new Point(realCell.Left+60,realCell.Top+60));
    Near(handles[0],realCell,"Actual mouse title-bar drag must dock");
    Console.WriteLine("PASS: actual mouse title-bar drag docks external window");
    RealDrag(handles[0],new Point(workBefore.Left+150,workBefore.Top+170));
    Native.SetWindowPos(handles[0],IntPtr.Zero,80,80,340,250,0x4014);Pump(800);
    if(Rect(handles[0]).Left>dock.Left)throw new Exception("Real drag out remains pinned");
    Console.WriteLine("PASS: actual mouse drag out releases window");
    dock.ReleaseAll();Native.SetWindowPos(handles[0],IntPtr.Zero,initial[0].X,initial[0].Y,initial[0].Width,initial[0].Height,0x4014);Pump(300);
    initial[0]=Rect(handles[0]);
    dock.Configure(2,33,Screen.PrimaryScreen.DeviceName);Pump(400);
    var cell=dock.Cells[0];dock.Drop(handles[0],new Point(cell.Left+20,cell.Top+20),DesktopDock.Capture(handles[0]));Pump(500);
    dock.Drop(handles[1],new Point(cell.Left+20,cell.Top+20),DesktopDock.Capture(handles[1]));Pump(500);Near(handles[0],initial[0],"Replacement restore");Near(handles[1],cell,"Replacement dock");
    dock.Drop(handles[1],new Point(workBefore.Left+20,workBefore.Top+20),null);
    Native.SetWindowPos(handles[1],IntPtr.Zero,100,100,350,240,0x4014);Pump(900);if(Rect(handles[1]).Left>dock.Left)throw new Exception("Dragged-out window still pinned");
    Console.WriteLine("PASS: occupied cell replacement restores previous window; dragging out releases pin");
    dock.Drop(handles[0],new Point(cell.Left+20,cell.Top+20),DesktopDock.Capture(handles[0]));Pump(500);
    using(var bitmap=new Bitmap(workBefore.Width,workBefore.Height)){using(var g=Graphics.FromImage(bitmap))g.CopyFromScreen(workBefore.Location,Point.Empty,workBefore.Size);bitmap.Save("apps/screen-layout/dist/app-preview.png",System.Drawing.Imaging.ImageFormat.Png);}
    dock.Close();Pump(700);Near(handles[0],initial[0],"Close restores window");
   }
   if(Screen.PrimaryScreen.WorkingArea!=workBefore)throw new Exception("Desktop work area not restored after exit");
   Console.WriteLine("PASS: exit restores desktop work area and docked windows");
  }finally{foreach(var p in processes){if(!p.HasExited){p.CloseMainWindow();if(!p.WaitForExit(1000))p.Kill();}p.Dispose();}}
 }
}
