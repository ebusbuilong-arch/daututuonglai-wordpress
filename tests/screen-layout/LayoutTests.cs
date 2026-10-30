using System;
using System.Drawing;
using System.Diagnostics;
using System.Collections.Generic;
using System.Reflection;
using System.Windows.Forms;
class LayoutTests {
 static BindingFlags Flags=BindingFlags.Instance|BindingFlags.NonPublic;
 static T Field<T>(MainForm f,string n){return (T)typeof(MainForm).GetField(n,Flags).GetValue(f);}
 static void Call(MainForm f,string n){typeof(MainForm).GetMethod(n,Flags).Invoke(f,null);}
 static void Pump(int ms){var t=Stopwatch.StartNew();while(t.ElapsedMilliseconds<ms){Application.DoEvents();System.Threading.Thread.Sleep(10);}}
 static Native.Rect Read(IntPtr h){Native.Rect r;if(Native.DwmGetWindowAttribute(h,9,out r,System.Runtime.InteropServices.Marshal.SizeOf(typeof(Native.Rect)))!=0)Native.GetWindowRect(h,out r);return r;}
 [STAThread] public static void Main(string[] args){
  Application.EnableVisualStyles();
  if(args.Length>0&&args[0]=="fixture"){
   var f=new Form{Text="DTTL test window "+args[1],StartPosition=FormStartPosition.Manual,Location=new Point(40+int.Parse(args[1])*20,60),Size=new Size(340,250)};
   f.Shown+=delegate{Console.WriteLine(f.Handle.ToInt64());Console.Out.Flush();};Application.Run(f);return;
  }
  int count=0;foreach(var area in new[]{new Rectangle(0,0,1366,728),new Rectangle(-1920,0,1920,1040),new Rectangle(0,-2160,3840,2120),new Rectangle(1920,0,1080,1880)})for(int k=0;k<5;k++)for(int ratio=30;ratio<=70;ratio+=10)foreach(int gap in new[]{0,8}){
   var cells=Layouts.Make(area,k,ratio,gap);long total=0;for(int i=0;i<cells.Length;i++){if(!area.Contains(cells[i])||cells[i].Width<=0||cells[i].Height<=0)throw new Exception("Out of bounds");total+=(long)cells[i].Width*cells[i].Height;for(int j=0;j<i;j++)if(cells[i].IntersectsWith(cells[j]))throw new Exception("Overlap");}if(gap==0&&total!=(long)area.Width*area.Height)throw new Exception("Missing area");count++;
  }
  Console.WriteLine("PASS: "+count+" geometry cases");
  var processes=new List<Process>();var handles=new List<IntPtr>();
  try {
   for(int i=0;i<4;i++){
    var p=Process.Start(new ProcessStartInfo(Assembly.GetExecutingAssembly().Location,"fixture "+i){UseShellExecute=false,RedirectStandardOutput=true});processes.Add(p);
    handles.Add(new IntPtr(long.Parse(p.StandardOutput.ReadLine())));
   }
   using(var form=new MainForm()){
    form.Show();Pump(200);Field<CheckBox>(form,"minimize").Checked=false;
    var combos=Field<ComboBox[]>(form,"windows");var layout=Field<ComboBox>(form,"layout");
    for(int i=0;i<4;i++)foreach(WindowItem item in combos[i].Items)if(item.Handle==handles[i]){combos[i].SelectedItem=item;break;}
    var initial=new Native.Rect[4];for(int i=0;i<4;i++)initial[i]=Read(handles[i]);
    for(int k=0;k<5;k++){
     layout.SelectedIndex=k;Call(form,"Apply");Pump(800);
     var monitor=Field<ComboBox>(form,"monitor");var screens=Field<Screen[]>(form,"screens");
     var expected=Layouts.Make(screens[monitor.SelectedIndex].WorkingArea,k,Field<TrackBar>(form,"ratio").Value,8);
     for(int i=0;i<expected.Length;i++){
      var actual=Read(handles[i]);var wanted=expected[i];
      if(Math.Abs(actual.L-wanted.Left)>4||Math.Abs(actual.T-wanted.Top)>4||Math.Abs(actual.R-wanted.Right)>4||Math.Abs(actual.B-wanted.Bottom)>4)throw new Exception("Native positioning mismatch layout="+k+" cell="+i+" got="+actual.L+","+actual.T+","+actual.R+","+actual.B+" expected="+wanted);
     }
     Call(form,"Undo");Pump(200);for(int i=0;i<4;i++){var a=Read(handles[i]);var b=initial[i];if(Math.Abs(a.L-b.L)>4||Math.Abs(a.T-b.T)>4||Math.Abs(a.R-b.R)>4||Math.Abs(a.B-b.B)>4)throw new Exception("Undo mismatch");}
    }
    Console.WriteLine("PASS: native positioning and undo for all 5 layouts, using 4 separate window processes");
    layout.SelectedIndex=0;combos[1].SelectedItem=combos[0].SelectedItem;Call(form,"Apply");
    if(!Field<Label>(form,"status").Text.Contains("Mỗi cửa sổ"))throw new Exception("Duplicate selection not rejected");
    Console.WriteLine("PASS: duplicate selection rejected");
    Call(form,"RefreshWindows");layout.SelectedIndex=2;
    using(var bitmap=new Bitmap(form.Width,form.Height)){form.DrawToBitmap(bitmap,new Rectangle(0,0,form.Width,form.Height));bitmap.Save("apps/screen-layout/dist/app-preview.png",System.Drawing.Imaging.ImageFormat.Png);}
    form.Close();
   }
  }finally{foreach(var p in processes){if(!p.HasExited){p.CloseMainWindow();if(!p.WaitForExit(1000))p.Kill();}p.Dispose();}}
 }
}
