using System;
using System.Collections.Generic;
using System.Drawing;
using System.IO;
using System.Runtime.InteropServices;
using System.Text;
using System.Windows.Forms;

[assembly: System.Reflection.AssemblyTitle("DTTL Chia màn hình")]
[assembly: System.Reflection.AssemblyVersion("0.1.0.0")]
[assembly: System.Reflection.AssemblyCompany("Đầu Tư Tương Lai")]

static class Native {
    public delegate bool EnumProc(IntPtr h, IntPtr p);
    [DllImport("user32.dll")] public static extern bool EnumWindows(EnumProc fn, IntPtr p);
    [DllImport("user32.dll")] public static extern bool IsWindowVisible(IntPtr h);
    [DllImport("user32.dll")] public static extern bool IsWindow(IntPtr h);
    [DllImport("user32.dll", CharSet=CharSet.Unicode)] public static extern int GetWindowText(IntPtr h, StringBuilder s, int n);
    [DllImport("user32.dll")] public static extern IntPtr GetWindow(IntPtr h, uint cmd);
    [DllImport("user32.dll")] public static extern bool GetWindowRect(IntPtr h, out Rect r);
    [DllImport("user32.dll")] public static extern bool GetWindowPlacement(IntPtr h, ref Placement p);
    [DllImport("user32.dll")] public static extern bool SetWindowPlacement(IntPtr h, ref Placement p);
    [DllImport("user32.dll")] public static extern uint GetWindowThreadProcessId(IntPtr h, out uint pid);
    [DllImport("user32.dll")] public static extern bool ShowWindowAsync(IntPtr h, int cmd);
    [DllImport("user32.dll", SetLastError=true)] public static extern bool SetWindowPos(IntPtr h, IntPtr after, int x, int y, int w, int height, uint flags);
    [DllImport("user32.dll")] public static extern bool RegisterHotKey(IntPtr h, int id, uint mod, uint key);
    [DllImport("user32.dll")] public static extern bool UnregisterHotKey(IntPtr h, int id);
    [DllImport("dwmapi.dll")] public static extern int DwmGetWindowAttribute(IntPtr h, int attr, out Rect r, int size);
    [DllImport("dwmapi.dll", EntryPoint="DwmGetWindowAttribute")] public static extern int Cloaked(IntPtr h, int attr, out int value, int size);
    [DllImport("user32.dll")] public static extern bool SetProcessDpiAwarenessContext(IntPtr context);
    [DllImport("shcore.dll")] public static extern int SetProcessDpiAwareness(int awareness);
    [StructLayout(LayoutKind.Sequential)] public struct Rect { public int L,T,R,B; }
    [StructLayout(LayoutKind.Sequential)] public struct Point { public int X,Y; }
    [StructLayout(LayoutKind.Sequential)] public struct Placement { public int Length, Flags, Show; public Point Min,Max; public Rect Normal; }
}
public static class Layouts {
    public static Rectangle[] Make(Rectangle area, int kind, int ratio, int gap) {
        int w=area.Width, h=area.Height;
        int x=w*Math.Max(30,Math.Min(70,ratio))/100, y=h/2;
        Rectangle[] r;
        switch(kind) {
            case 1: r=new[]{new Rectangle(0,0,w,y),new Rectangle(0,y,w,h-y)}; break;
            case 2: r=new[]{new Rectangle(0,0,x,h),new Rectangle(x,0,w-x,y),new Rectangle(x,y,w-x,h-y)}; break;
            case 3: r=new[]{new Rectangle(0,0,w/3,h),new Rectangle(w/3,0,2*w/3-w/3,h),new Rectangle(2*w/3,0,w-2*w/3,h)}; break;
            case 4: r=new[]{new Rectangle(0,0,x,y),new Rectangle(x,0,w-x,y),new Rectangle(0,y,x,h-y),new Rectangle(x,y,w-x,h-y)}; break;
            default: r=new[]{new Rectangle(0,0,x,h),new Rectangle(x,0,w-x,h)}; break;
        }
        for(int i=0;i<r.Length;i++) { r[i].Offset(area.Location); int inset=Math.Min(gap/2, Math.Max(0,Math.Min(r[i].Width,r[i].Height)/4)); r[i].Inflate(-inset,-inset); }
        return r;
    }
}
class WindowItem {
    public IntPtr Handle; public uint Pid; public string Title;
    public override string ToString(){return Title;}
}
class SavedWindow { public WindowItem Item; public Native.Placement Placement; }
class Preview : Control {
    public int Kind, Ratio=50;
    public Preview(){DoubleBuffered=true; BackColor=Color.FromArgb(235,241,249);}
    protected override void OnPaint(PaintEventArgs e) {
        base.OnPaint(e); var a=ClientRectangle; a.Inflate(-12,-12);
        var cells=Layouts.Make(a,Kind,Ratio,10);
        for(int i=0;i<cells.Length;i++) {
            using(var b=new SolidBrush(i==0?Color.FromArgb(0,51,153):Color.FromArgb(58,108,187))) e.Graphics.FillRectangle(b,cells[i]);
            TextRenderer.DrawText(e.Graphics,"Ô "+(i+1),Font,cells[i],Color.White,TextFormatFlags.HorizontalCenter|TextFormatFlags.VerticalCenter);
        }
    }
}
class MainForm : Form {
    ComboBox layout=new ComboBox(), monitor=new ComboBox();
    ComboBox[] windows=new ComboBox[4]; Label[] labels=new Label[4];
    TrackBar ratio=new TrackBar(); Label ratioText=new Label(), status=new Label();
    Preview preview=new Preview(); CheckBox minimize=new CheckBox();
    List<SavedWindow> undo=new List<SavedWindow>(); List<WindowItem> items=new List<WindowItem>();
    Screen[] screens; Button restore; bool hotkey, restoringSettings, applying;
    string settings=Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),"DTTL","ScreenLayout.ini");
    public MainForm() {
        Text="DTTL • Chia màn hình 0.1 beta"; Font=new Font("Segoe UI",10); BackColor=Color.White;
        AutoScaleMode=AutoScaleMode.Dpi; ClientSize=new Size(650,660); MinimumSize=new Size(610,650);
        StartPosition=FormStartPosition.CenterScreen;
        var root=new TableLayoutPanel { Dock=DockStyle.Fill,Padding=new Padding(22),ColumnCount=2, RowCount=12 };
        root.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute,145)); root.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,100));
        Controls.Add(root);
        var title=new Label {Text="Sắp xếp gọn. Làm việc dễ hơn.",Font=new Font(Font.FontFamily,17,FontStyle.Bold),AutoSize=true,Margin=new Padding(0,0,0,12)};
        root.Controls.Add(title,0,0); root.SetColumnSpan(title,2);
        AddRow(root,"Bố cục",layout,1); layout.DropDownStyle=ComboBoxStyle.DropDownList;
        layout.Items.AddRange(new object[]{"2 ô • trái / phải","2 ô • trên / dưới","3 ô • một lớn, hai nhỏ","3 ô • ba cột","4 ô • lưới"});
        AddRow(root,"Màn hình",monitor,2); monitor.DropDownStyle=ComboBoxStyle.DropDownList;
        preview.Height=130; preview.Dock=DockStyle.Fill; root.Controls.Add(preview,0,3); root.SetColumnSpan(preview,2);
        var rpanel=new FlowLayoutPanel{AutoSize=true,Dock=DockStyle.Fill}; ratio.Minimum=30;ratio.Maximum=70;ratio.Value=50;ratio.TickFrequency=10;ratio.Width=230;ratio.Height=42;
        ratio.AccessibleName="Tỷ lệ phần bên trái"; ratioText.AutoSize=true; rpanel.Controls.Add(ratio);rpanel.Controls.Add(ratioText);AddRow(root,"Tỷ lệ bên trái",rpanel,4);
        for(int i=0;i<4;i++) { windows[i]=new ComboBox{DropDownStyle=ComboBoxStyle.DropDownList,DropDownWidth=470}; labels[i]=AddRow(root,"Ô "+(i+1),windows[i],i+5); }
        var options=new FlowLayoutPanel{Dock=DockStyle.Fill,AutoSize=true};
        var refresh=Button("Làm mới cửa sổ",delegate{RefreshWindows();}); options.Controls.Add(refresh);
        minimize.Text="Thu nhỏ app sau khi xếp";minimize.AutoSize=true;minimize.Checked=true;options.Controls.Add(minimize);
        root.Controls.Add(options,0,9);root.SetColumnSpan(options,2);
        var actions=new FlowLayoutPanel{Dock=DockStyle.Fill,AutoSize=true};
        var apply=Button("Sắp xếp",delegate{Apply();}); apply.BackColor=Color.FromArgb(0,51,153);apply.ForeColor=Color.White;
        restore=Button("Hoàn tác",delegate{Undo();}); restore.Enabled=false;
        var help=Button("Hướng dẫn",delegate{MessageBox.Show(this,"1. Mở sẵn các cửa sổ cần dùng.\n2. Chọn bố cục và màn hình.\n3. Chọn cửa sổ cho từng ô rồi bấm Sắp xếp.\n\nCtrl + Alt + F8: sắp xếp lại.\nHoàn tác: trả cửa sổ về vị trí trước lần xếp gần nhất.\nBố cục được lưu tự động; cửa sổ cần chọn lại khi mở app.\n\nMột số ứng dụng có kích thước tối thiểu hoặc chạy với quyền quản trị sẽ không vừa ô. Hãy dùng ít ô hơn.\nBản này dành cho Windows 10/11, không có quảng cáo và không gửi dữ liệu mạng.","DTTL • Hướng dẫn");});
        actions.Controls.AddRange(new Control[]{apply,restore,help});root.Controls.Add(actions,0,10);root.SetColumnSpan(actions,2);
        status.AutoSize=true;status.MaximumSize=new Size(580,0);status.ForeColor=Color.FromArgb(65,80,100);status.Margin=new Padding(0,12,0,0);root.Controls.Add(status,0,11);root.SetColumnSpan(status,2);
        layout.SelectedIndexChanged+=delegate{UpdatePreview();Save();};ratio.ValueChanged+=delegate{UpdatePreview();Save();};monitor.SelectedIndexChanged+=delegate{Save();};minimize.CheckedChanged+=delegate{Save();};
        restoringSettings=true; RefreshMonitors();layout.SelectedIndex=0; LoadSettings(); restoringSettings=false; UpdatePreview();
        Shown+=delegate {RefreshWindows();hotkey=Native.RegisterHotKey(Handle,1,0x4003,0x77); if(!hotkey)status.Text="Phím tắt đang được ứng dụng khác sử dụng. Anh/chị vẫn có thể bấm Sắp xếp.";};
        FormClosed+=delegate{if(hotkey)Native.UnregisterHotKey(Handle,1);};
    }
    Button Button(string text, EventHandler action){var b=new Button{Text=text,AutoSize=true,Height=36,Padding=new Padding(9,4,9,4),FlatStyle=FlatStyle.Flat};b.Click+=action;return b;}
    Label AddRow(TableLayoutPanel p,string text,Control c,int row){var l=new Label{Text=text,AutoSize=true,Anchor=AnchorStyles.Left,Margin=new Padding(0,8,0,8)};c.Dock=DockStyle.Fill;c.Margin=new Padding(0,5,0,5);p.Controls.Add(l,0,row);p.Controls.Add(c,1,row);return l;}
    void RefreshMonitors(){screens=Screen.AllScreens;monitor.Items.Clear();foreach(var s in screens)monitor.Items.Add(s.DeviceName+" • "+s.Bounds.Width+" × "+s.Bounds.Height+(s.Primary?" • Chính":""));monitor.SelectedIndex=0;}
    void UpdatePreview(){int k=Math.Max(0,layout.SelectedIndex); preview.Kind=k;preview.Ratio=ratio.Value;preview.Invalidate();ratio.Enabled=k==0||k==2||k==4;ratioText.Text=ratio.Value+"%";int n=Layouts.Make(new Rectangle(0,0,1000,800),k,ratio.Value,0).Length;for(int i=0;i<4;i++){windows[i].Enabled=i<n;labels[i].Enabled=i<n;}}
    void RefreshWindows(){
        IntPtr[] chosen=new IntPtr[4];for(int i=0;i<4;i++){var old=windows[i].SelectedItem as WindowItem;if(old!=null)chosen[i]=old.Handle;}
        items.Clear();items.Add(new WindowItem{Title="— Để trống ô này —",Handle=IntPtr.Zero});
        Native.EnumWindows(delegate(IntPtr h,IntPtr p){
            if(h==Handle||!Native.IsWindowVisible(h)||Native.GetWindow(h,4)!=IntPtr.Zero)return true;
            var title=new StringBuilder(512);Native.GetWindowText(h,title,title.Capacity);if(title.Length==0)return true;
            int cloaked; if(Native.Cloaked(h,14,out cloaked,4)==0&&cloaked!=0)return true;
            uint pid;Native.GetWindowThreadProcessId(h,out pid); if(pid==(uint)System.Diagnostics.Process.GetCurrentProcess().Id)return true;
            items.Add(new WindowItem{Handle=h,Pid=pid,Title=title.ToString()});return true;
        },IntPtr.Zero);
        for(int i=0;i<4;i++){windows[i].Items.Clear();foreach(var item in items)windows[i].Items.Add(item);windows[i].SelectedIndex=0;for(int j=1;j<items.Count;j++)if(items[j].Handle==chosen[i])windows[i].SelectedIndex=j;}
        status.Text="Chọn cửa sổ cho từng ô. Ctrl + Alt + F8 để sắp xếp lại nhanh.";
    }
    bool Valid(WindowItem item){uint pid;return item!=null&&item.Handle!=IntPtr.Zero&&Native.IsWindow(item.Handle)&&Native.GetWindowThreadProcessId(item.Handle,out pid)!=0&&pid==item.Pid;}
    void Apply(){
        if(applying||monitor.SelectedIndex<0)return;string device=screens[monitor.SelectedIndex].DeviceName;
        Screen target=null;foreach(var s in Screen.AllScreens)if(s.DeviceName==device)target=s;
        if(target==null){RefreshMonitors();status.Text="Màn hình đã thay đổi. Chọn lại màn hình rồi thử lại.";return;}
        var cells=Layouts.Make(target.WorkingArea,layout.SelectedIndex,ratio.Value,8);var selected=new List<WindowItem>();var ids=new HashSet<IntPtr>();
        for(int i=0;i<cells.Length;i++){var it=windows[i].SelectedItem as WindowItem;if(it!=null&&it.Handle!=IntPtr.Zero){if(!Valid(it)){status.Text="Một cửa sổ đã đóng. Bấm Làm mới cửa sổ rồi chọn lại.";return;}if(!ids.Add(it.Handle)){status.Text="Mỗi cửa sổ chỉ chọn cho một ô.";return;}selected.Add(it);}}
        if(selected.Count==0){status.Text="Chọn ít nhất một cửa sổ trước khi sắp xếp.";return;}
        var saved=new List<SavedWindow>();foreach(var it in selected){var p=new Native.Placement();p.Length=Marshal.SizeOf(p);if(!Native.GetWindowPlacement(it.Handle,ref p)){status.Text="Không đọc được vị trí cửa sổ. Chưa thực hiện sắp xếp.";return;}saved.Add(new SavedWindow{Item=it,Placement=p});}
        undo=saved;restore.Enabled=false;applying=true;int failed=0;
        var assignments=new WindowItem[cells.Length];for(int i=0;i<cells.Length;i++)assignments[i]=windows[i].SelectedItem as WindowItem;
        for(int i=0;i<cells.Length;i++){var it=windows[i].SelectedItem as WindowItem;if(it==null||it.Handle==IntPtr.Zero)continue;
            Native.ShowWindowAsync(it.Handle,9);
            var r=cells[i];
            // Restore first, then move without activating or changing the Z-order. Async prevents a hung target blocking this app.
            if(!Native.SetWindowPos(it.Handle,IntPtr.Zero,r.X,r.Y,r.Width,r.Height,0x4014))failed++;
        }
        // A second pass after restoration accounts for invisible resize borders and target monitor DPI.
        var timer=new Timer{Interval=250};timer.Tick+=delegate {timer.Stop();timer.Dispose();int inaccurate=0;
            for(int i=0;i<cells.Length;i++){var it=assignments[i];if(!Valid(it))continue;Native.Rect outer,visible;var r=cells[i];
                if(Native.GetWindowRect(it.Handle,out outer)&&Native.DwmGetWindowAttribute(it.Handle,9,out visible,Marshal.SizeOf(typeof(Native.Rect)))==0){
                    int l=visible.L-outer.L,t=visible.T-outer.T,rr=outer.R-visible.R,b=outer.B-visible.B;
                    if(!Native.SetWindowPos(it.Handle,IntPtr.Zero,r.X-l,r.Y-t,r.Width+l+rr,r.Height+t+b,0x4014))inaccurate++;
                }
            }
            applying=false;restore.Enabled=true;status.Text=(failed+inaccurate>0)?"Một số cửa sổ không cho phép di chuyển. Dùng Hoàn tác nếu cần.":"Đã gửi yêu cầu sắp xếp. Cửa sổ có giới hạn kích thước có thể không vừa ô.";
            if(minimize.Checked&&failed+inaccurate==0)WindowState=FormWindowState.Minimized;
        };timer.Start(); Save();
    }
    void Undo(){if(applying)return;int failed=0;foreach(var s in undo){var p=s.Placement;if(!Valid(s.Item)||!Native.SetWindowPlacement(s.Item.Handle,ref p))failed++;}undo.Clear();restore.Enabled=false;status.Text=failed==0?"Đã hoàn tác vị trí cửa sổ.":"Đã hoàn tác các cửa sổ còn mở và cho phép điều khiển.";}
    void Save(){if(restoringSettings)return;try{Directory.CreateDirectory(Path.GetDirectoryName(settings));File.WriteAllLines(settings,new[]{layout.SelectedIndex.ToString(),ratio.Value.ToString(),monitor.SelectedIndex>=0?screens[monitor.SelectedIndex].DeviceName:"",minimize.Checked?"1":"0"});}catch(IOException){}catch(UnauthorizedAccessException){}}
    void LoadSettings(){try{var v=File.ReadAllLines(settings);int n;if(v.Length>=4){if(int.TryParse(v[0],out n)&&n>=0&&n<5)layout.SelectedIndex=n;if(int.TryParse(v[1],out n)&&n>=30&&n<=70)ratio.Value=n;for(int i=0;i<screens.Length;i++)if(screens[i].DeviceName==v[2])monitor.SelectedIndex=i;minimize.Checked=v[3]=="1";}}catch(IOException){}catch(UnauthorizedAccessException){}}
    protected override void WndProc(ref Message m){if(m.Msg==0x312&&m.WParam.ToInt32()==1)Apply();base.WndProc(ref m);}
}
static class Program {
    [STAThread] static void Main(){
        try {Native.SetProcessDpiAwarenessContext(new IntPtr(-4));}catch(EntryPointNotFoundException){try{Native.SetProcessDpiAwareness(2);}catch{}}
        Application.EnableVisualStyles();Application.SetCompatibleTextRenderingDefault(false);Application.Run(new MainForm());
    }
}
