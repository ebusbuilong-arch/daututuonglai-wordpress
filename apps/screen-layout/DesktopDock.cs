using System;
using System.Collections.Generic;
using System.Drawing;
using System.IO;
using System.Runtime.InteropServices;
using System.Windows.Forms;

static class ShellNative {
    [StructLayout(LayoutKind.Sequential)] internal struct AppBar {
        public uint Size; public IntPtr Window; public uint Callback, Edge; public Native.Rect Rect; public IntPtr Param;
    }
    internal delegate void WinEvent(IntPtr hook,uint ev,IntPtr window,int obj,int child,uint thread,uint time);
    [DllImport("shell32.dll")] internal static extern UIntPtr SHAppBarMessage(uint message, ref AppBar data);
    [DllImport("user32.dll",CharSet=CharSet.Unicode)] internal static extern uint RegisterWindowMessage(string name);
    [DllImport("user32.dll")] internal static extern IntPtr SetWinEventHook(uint min,uint max,IntPtr module,WinEvent callback,uint pid,uint thread,uint flags);
    [DllImport("user32.dll")] internal static extern bool UnhookWinEvent(IntPtr hook);
    [DllImport("user32.dll")] internal static extern bool GetCursorPos(out Native.Point point);
    [DllImport("user32.dll")] internal static extern bool IsIconic(IntPtr window);
    [DllImport("user32.dll")] internal static extern IntPtr GetForegroundWindow();
    [DllImport("user32.dll")] internal static extern bool GetGUIThreadInfo(uint thread,ref GuiInfo info);
    [StructLayout(LayoutKind.Sequential)] internal struct GuiInfo {
        public int Size; public uint Flags; public IntPtr Active,Focus,Capture,MenuOwner,MoveSize,Caret; public Native.Rect CaretRect;
    }
    [DllImport("user32.dll")] internal static extern bool IsZoomed(IntPtr window);
}

internal sealed class DockedWindow {
    internal IntPtr Handle; internal uint Pid; internal Native.Placement Original;
}

internal static class DockGeometry {
    internal static Rectangle Cell(Rectangle bounds,int count,int index) {
        int top=bounds.Top+100, height=Math.Max(0,bounds.Height-100);
        int start=top+height*index/count, end=top+height*(index+1)/count;
        return Rectangle.FromLTRB(bounds.Left+6,start+30,bounds.Right-6,end-6);
    }
    internal static int Hit(Rectangle bounds,int count,Point point) {
        // Windows can constrain a dragged title bar to the desktop work-area edge.
        // Accept the adjacent 12px edge and the full row, including its heading.
        if(point.X<bounds.Left-12||point.X>=bounds.Right||point.Y<bounds.Top+100||point.Y>=bounds.Bottom)return -1;
        for(int i=0;i<count;i++){
            int top=bounds.Top+100+(bounds.Height-100)*i/count;
            int bottom=bounds.Top+100+(bounds.Height-100)*(i+1)/count;
            if(point.Y>=top&&point.Y<bottom)return i;
        }
        return -1;
    }
}

internal sealed class DesktopDock : Form {
    const uint Callback=0x8001;
    readonly ShellNative.WinEvent eventCallback;
    readonly DockedWindow[] slots=new DockedWindow[3];
    readonly Button[] release=new Button[3];
    readonly Button[] choose=new Button[3];
    readonly Timer dragWatch=new Timer{Interval=40};
    int hover=-1;
    readonly Timer maintenance=new Timer{Interval=600};
    readonly Timer settle=new Timer{Interval=220};
    readonly NotifyIcon tray=new NotifyIcon();
    readonly Label hint=new Label();
    readonly string config=Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),"DTTL","DesktopDock.ini");
    IntPtr hook, moving; DockedWindow beforeMove;
    bool registered, positioning, closing, pending, suspended;
    int count=2, percent=33; string device; uint taskbarRestart;
    internal Rectangle[] Cells { get { var r=new Rectangle[count];for(int i=0;i<count;i++)r[i]=DockGeometry.Cell(Bounds,count,i);return r; } }
    internal int SlotCount {get{return count;}}
    internal bool Reserved {get{return registered;}}
    internal DesktopDock(bool readSettings) {
        Text="DTTL • Vùng phụ 0.2.1 beta"; FormBorderStyle=FormBorderStyle.None;
        ShowInTaskbar=false; StartPosition=FormStartPosition.Manual;
        BackColor=Color.FromArgb(237,246,243); ForeColor=Color.FromArgb(20,36,59);
        Font=new Font("Segoe UI",10); DoubleBuffered=true; AutoScaleMode=AutoScaleMode.None;
        device=Screen.PrimaryScreen.DeviceName;
        if(readSettings)LoadSettings();
        var title=new Label{Text="DTTL • Vùng phụ",AutoSize=true,Location=new Point(12,10),Font=new Font(Font,FontStyle.Bold)};
        Controls.Add(title);
        var menuButton=new Button{Text="Tùy chọn ▾",Location=new Point(12,37),Size=new Size(120,29),FlatStyle=FlatStyle.Flat};
        menuButton.Click+=delegate{MakeMenu().Show(menuButton,new Point(0,menuButton.Height));};Controls.Add(menuButton);
        hint.Text="Kéo thanh tiêu đề cửa sổ vào ô bên dưới";hint.Location=new Point(12,73);hint.Height=24;Controls.Add(hint);
        for(int i=0;i<3;i++){int index=i;release[i]=new Button{Text="Trả cửa sổ",Size=new Size(105,25),FlatStyle=FlatStyle.Flat};release[i].Click+=delegate{Release(index,true);};Controls.Add(release[i]);
            choose[i]=new Button{Text="Chọn cửa sổ",Size=new Size(110,25),FlatStyle=FlatStyle.Flat};
            choose[i].Click+=delegate{ChooseWindow(index);};Controls.Add(choose[i]);}
        eventCallback=OnWindowEvent;
        Shown+=delegate {
            taskbarRestart=ShellNative.RegisterWindowMessage("TaskbarCreated");
            RegisterBar();
            hook=ShellNative.SetWinEventHook(0x000A,0x000B,IntPtr.Zero,eventCallback,0,0,2);
            if(hook==IntPtr.Zero){MessageBox.Show("Không bật được kéo thả cửa sổ. Hãy đóng và mở lại ứng dụng.",Text);Close();return;}
            tray.Icon=SystemIcons.Application;tray.Text="DTTL • Chia màn hình";tray.Visible=true;
            tray.MouseClick+=delegate(object sender,MouseEventArgs e){if(e.Button==MouseButtons.Right){tray.ContextMenuStrip=MakeMenu();tray.ContextMenuStrip.Show(Cursor.Position);}else{Show();Activate();}};
            maintenance.Start();dragWatch.Start();
        };
        maintenance.Tick+=delegate{Maintain();};
        dragWatch.Tick+=delegate{WatchDrag();};
        settle.Tick+=delegate{settle.Stop();FitAll();};
        Activated+=delegate{if(registered&&!closing)BeginInvoke((Action)delegate{FitAll();});};
        FormClosed+=delegate{Shutdown();};
    }
    internal DesktopDock():this(true){}
    Screen Target(){foreach(var s in Screen.AllScreens)if(s.DeviceName==device)return s;return Screen.PrimaryScreen;}
    ShellNative.AppBar Data(){return new ShellNative.AppBar{Size=(uint)Marshal.SizeOf(typeof(ShellNative.AppBar)),Window=Handle,Callback=Callback,Edge=2};}
    void RegisterBar(){if(registered||closing)return;var d=Data();registered=ShellNative.SHAppBarMessage(0,ref d)!=UIntPtr.Zero;if(!registered){MessageBox.Show("Không giữ được vùng phụ. Hãy kiểm tra ứng dụng chia màn hình khác rồi thử lại.",Text);Close();return;}PositionBar();}
    void RemoveBar(){if(!registered)return;registered=false;var d=Data();ShellNative.SHAppBarMessage(1,ref d);}
    void PositionBar(){
        if(!registered||positioning||closing)return;positioning=true;
        try {
            var screen=Target();device=screen.DeviceName;var b=screen.Bounds;
            int width=Math.Max(320,Math.Min(b.Width/2,b.Width*percent/100));
            var d=Data();d.Rect=new Native.Rect{L=b.Right-width,T=b.Top,R=b.Right,B=b.Bottom};
            ShellNative.SHAppBarMessage(2,ref d);d.Rect.L=d.Rect.R-width;
            ShellNative.SHAppBarMessage(3,ref d);
            SetBounds(d.Rect.L,d.Rect.T,d.Rect.R-d.Rect.L,d.Rect.B-d.Rect.T);
            Native.SetWindowPos(Handle,new IntPtr(-2),0,0,0,0,0x13);
            hint.Width=Math.Max(100,ClientSize.Width-24);
            for(int i=0;i<3;i++){release[i].Visible=i<count;choose[i].Visible=i<count; if(i<count){var cell=Cells[i];release[i].Location=new Point(ClientSize.Width-117,cell.Top-Top-27);choose[i].Location=new Point(75,cell.Top-Top-27);}}
            Invalidate();settle.Stop();settle.Start();
        } finally{positioning=false;}
    }
    internal void Configure(int number,int width,string monitor){
        ReleaseAll();count=Math.Max(1,Math.Min(3,number));percent=Math.Max(25,Math.Min(45,width));device=monitor;
        PositionBar();SaveSettings();
    }
    ContextMenuStrip MakeMenu(){
        var menu=new ContextMenuStrip();
        for(int i=1;i<=3;i++){int n=i;var item=new ToolStripMenuItem(n+" ô phụ"){Checked=count==n};item.Click+=delegate{Configure(n,percent,device);};menu.Items.Add(item);}
        var widths=new ToolStripMenuItem("Chiều rộng vùng phụ");foreach(int value in new[]{25,33,40,45}){int v=value;var item=new ToolStripMenuItem(v+"%"){Checked=percent==v};item.Click+=delegate{Configure(count,v,device);};widths.DropDownItems.Add(item);}menu.Items.Add(widths);
        var monitors=new ToolStripMenuItem("Chọn màn hình");foreach(var screen in Screen.AllScreens){string id=screen.DeviceName;var item=new ToolStripMenuItem(id+(screen.Primary?" • Chính":"")){Checked=device==id};item.Click+=delegate{Configure(count,percent,id);};monitors.DropDownItems.Add(item);}menu.Items.Add(monitors);
        menu.Items.Add(new ToolStripSeparator());menu.Items.Add("Trả tất cả cửa sổ",null,delegate{ReleaseAll();});
        menu.Items.Add("Hướng dẫn",null,delegate{MessageBox.Show(this,"Desktop chính vẫn dùng bình thường.\n\nKéo thanh tiêu đề của một cửa sổ đang mở, thả vào vùng trống của ô phụ. Mỗi ô chứa một cửa sổ. Kéo cửa sổ ra vùng chính để bỏ ghim, hoặc bấm Trả cửa sổ để khôi phục vị trí cũ.\n\nTùy chọn: 1–3 ô phụ, chiều rộng và màn hình. Đóng ứng dụng sẽ trả vùng desktop và các cửa sổ về trước khi ghim.\n\nỨng dụng có kích thước tối thiểu lớn có thể không vừa ô: chọn ít ô hơn hoặc tăng chiều rộng. Ứng dụng chạy quyền quản trị có thể không di chuyển được.\n\nChế độ toàn màn hình của game/video có thể che vùng phụ. Đây là chia vùng hiển thị, không tạo màn hình ảo mới.",Text);});
        menu.Items.Add("Thoát và trả desktop",null,delegate{Close();});menu.Closed+=delegate{if(tray.ContextMenuStrip!=menu)menu.Dispose();};return menu;
    }
    internal static bool Valid(DockedWindow w){uint pid;return w!=null&&Native.IsWindow(w.Handle)&&Native.GetWindowThreadProcessId(w.Handle,out pid)!=0&&pid==w.Pid;}
    internal static DockedWindow Capture(IntPtr h){
        uint pid;if(h==IntPtr.Zero||!Native.IsWindowVisible(h)||Native.GetWindow(h,4)!=IntPtr.Zero||Native.GetWindowThreadProcessId(h,out pid)==0||pid==(uint)System.Diagnostics.Process.GetCurrentProcess().Id)return null;
        var title=new System.Text.StringBuilder(256);Native.GetWindowText(h,title,title.Capacity);if(title.Length==0)return null;
        var p=new Native.Placement{Length=Marshal.SizeOf(typeof(Native.Placement))};if(!Native.GetWindowPlacement(h,ref p))return null;
        return new DockedWindow{Handle=h,Pid=pid,Original=p};
    }
    void ChooseWindow(int index){
        var menu=new ContextMenuStrip();
        Native.EnumWindows(delegate(IntPtr h,IntPtr unused){
            var saved=Capture(h);int cloaked;
            if(!Valid(saved)||(Native.Cloaked(h,14,out cloaked,4)==0&&cloaked!=0))return true;
            var title=new System.Text.StringBuilder(256);Native.GetWindowText(h,title,title.Capacity);
            menu.Items.Add(title.ToString(),null,delegate{var c=Cells[index];Drop(h,new Point(c.Left+20,c.Top+20),saved);});return true;
        },IntPtr.Zero);
        if(menu.Items.Count==0)menu.Items.Add("Mở ứng dụng cần đưa sang trước").Enabled=false;
        menu.Closed+=delegate{menu.Dispose();};menu.Show(choose[index],new Point(0,choose[index].Height));
    }
    void BeginMove(IntPtr window){
        if(window==moving)return;
        var saved=Capture(window);if(!Valid(saved))return;
        moving=window;beforeMove=saved;
    }
    void EndMove(IntPtr window){
        var saved=moving==window?beforeMove:Capture(window);
        moving=IntPtr.Zero;beforeMove=null;hover=-1;Invalidate();
        Native.Point p;if(ShellNative.GetCursorPos(out p))Drop(window,new Point(p.X,p.Y),saved);
    }
    void WatchDrag(){
        if(closing)return;
        IntPtr foreground=ShellNative.GetForegroundWindow();uint pid;
        uint thread=Native.GetWindowThreadProcessId(foreground,out pid);
        var info=new ShellNative.GuiInfo{Size=Marshal.SizeOf(typeof(ShellNative.GuiInfo))};
        bool inMove=thread!=0&&ShellNative.GetGUIThreadInfo(thread,ref info)&&(info.Flags&2)!=0;
        if(inMove&&info.MoveSize!=IntPtr.Zero)BeginMove(info.MoveSize);
        // Polling supplements applications which do not deliver the accessibility move events.
        if(moving!=IntPtr.Zero&&!inMove){EndMove(moving);return;}
        Native.Point p;int next=-1;
        if(moving!=IntPtr.Zero&&ShellNative.GetCursorPos(out p))next=DockGeometry.Hit(Bounds,count,new Point(p.X,p.Y));
        if(next!=hover){hover=next;Invalidate();}
    }
    void OnWindowEvent(IntPtr eventHook,uint ev,IntPtr window,int obj,int child,uint thread,uint time){
        if(closing||window==Handle||obj!=0)return;
        if(ev==0xA)BeginMove(window);
        else if(ev==0xB)EndMove(window);
    }
    internal void Drop(IntPtr window,Point cursor,DockedWindow saved){
        int target=DockGeometry.Hit(Bounds,count,cursor),old=-1;
        for(int i=0;i<count;i++)if(slots[i]!=null&&slots[i].Handle==window){old=i;saved=slots[i];break;}
        if(target<0){if(old>=0)Release(old,false);return;}
        if(!Valid(saved))return;
        if(old>=0)slots[old]=null;
        Release(target,true);slots[target]=saved;
        Native.ShowWindowAsync(window,9);settle.Stop();settle.Start();Invalidate();
    }
    internal void Release(int index,bool restore){var w=slots[index];slots[index]=null;if(restore&&Valid(w)){var p=w.Original;Native.SetWindowPlacement(w.Handle,ref p);}Invalidate();}
    internal void ReleaseAll(){for(int i=0;i<3;i++)Release(i,true);}
    void FitAll(){
        if(closing||suspended)return;
        Native.SetWindowPos(Handle,new IntPtr(1),0,0,0,0,0x13);
        for(int i=0;i<count;i++)if(Valid(slots[i])&&slots[i].Handle!=moving&&!ShellNative.IsIconic(slots[i].Handle))Fit(slots[i].Handle,Cells[i]);
        Invalidate();
    }
    static bool Fit(IntPtr window,Rectangle r){
        if(ShellNative.IsZoomed(window))Native.ShowWindowAsync(window,9);
        Native.Rect o,v;int l=0,t=0,rr=0,b=0;
        if(Native.GetWindowRect(window,out o)&&Native.DwmGetWindowAttribute(window,9,out v,Marshal.SizeOf(typeof(Native.Rect)))==0){l=v.L-o.L;t=v.T-o.T;rr=o.R-v.R;b=o.B-v.B;}
        return Native.SetWindowPos(window,IntPtr.Zero,r.X-l,r.Y-t,r.Width+l+rr,r.Height+t+b,0x4010);
    }
    void Maintain(){
        if(closing)return;
        if(pending){pending=false;PositionBar();}
        for(int i=0;i<count;i++){
            var w=slots[i];release[i].Enabled=Valid(w);
            if(w!=null&&!Valid(w)){slots[i]=null;Invalidate();continue;}
            if(!Valid(w)||w.Handle==moving||ShellNative.IsIconic(w.Handle)||suspended)continue;
            Native.Rect r;if(!Native.GetWindowRect(w.Handle,out r))continue;
            var c=Cells[i];
            // Only repair escaped/maximized windows; minimum-size windows are not resized repeatedly.
            if(ShellNative.IsZoomed(w.Handle)||r.L<c.Left-20||r.T<c.Top-20||r.L>c.Right||r.T>c.Bottom)Fit(w.Handle,c);
        }
    }
    protected override void OnPaint(PaintEventArgs e){
        base.OnPaint(e);
        for(int i=0;i<count;i++){
            var rect=Cells[i];rect.Offset(-Left,-Top);
            using(var pen=new Pen(Color.FromArgb(155,197,184),1))e.Graphics.DrawRectangle(pen,rect);
            TextRenderer.DrawText(e.Graphics,"Ô phụ "+(i+1),Font,new Point(12,rect.Top-25),ForeColor);
            if(i==hover){using(var pen=new Pen(Color.FromArgb(8,127,112),4))e.Graphics.DrawRectangle(pen,rect);}
            if(!Valid(slots[i]))TextRenderer.DrawText(e.Graphics,"Kéo cửa sổ vào đây",Font,rect,Color.FromArgb(8,127,112),TextFormatFlags.HorizontalCenter|TextFormatFlags.VerticalCenter);
        }
    }
    protected override void WndProc(ref Message m){
        if(m.Msg==Callback){
            if(m.WParam.ToInt32()==1&&!positioning)pending=true;
            if(m.WParam.ToInt32()==2){suspended=m.LParam!=IntPtr.Zero;}
        }
        if(taskbarRestart!=0&&m.Msg==taskbarRestart){registered=false;BeginInvoke((Action)delegate{RegisterBar();});}
        if(m.Msg==0x7E){ReleaseAll();pending=true;}
        if(m.Msg==0x2E0)pending=true;
        if(m.Msg==0x6&&registered){var d=Data();ShellNative.SHAppBarMessage(6,ref d);}
        base.WndProc(ref m);
    }
    void Shutdown(){if(closing)return;closing=true;maintenance.Stop();settle.Stop();dragWatch.Stop();dragWatch.Dispose();if(hook!=IntPtr.Zero)ShellNative.UnhookWinEvent(hook);RemoveBar();ReleaseAll();tray.Visible=false;tray.Dispose();maintenance.Dispose();settle.Dispose();}
    void SaveSettings(){try{Directory.CreateDirectory(Path.GetDirectoryName(config));File.WriteAllLines(config,new[]{count.ToString(),percent.ToString(),device});}catch(IOException){}catch(UnauthorizedAccessException){}}
    void LoadSettings(){try{var v=File.ReadAllLines(config);int n;if(v.Length>=3){if(int.TryParse(v[0],out n)&&n>=1&&n<=3)count=n;if(int.TryParse(v[1],out n)&&n>=25&&n<=45)percent=n;device=v[2];}}catch(IOException){}catch(UnauthorizedAccessException){}}
}
static class Program {
    [STAThread] static void Main(){
        bool first;using(var mutex=new System.Threading.Mutex(true,"Local\\DTTL.DesktopDock",out first)){
            if(!first){MessageBox.Show("DTTL đang chạy. Bấm biểu tượng ứng dụng ở khay hệ thống để mở tùy chọn.");return;}
            try{Native.SetProcessDpiAwarenessContext(new IntPtr(-4));}catch(EntryPointNotFoundException){try{Native.SetProcessDpiAwareness(2);}catch{}}
            Application.EnableVisualStyles();Application.SetCompatibleTextRenderingDefault(false);Application.Run(new DesktopDock());
        }
    }
}
