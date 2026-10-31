using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.Drawing.Imaging;
using System.IO;
using System.Runtime.InteropServices;
using System.Text.RegularExpressions;
using System.Threading;
using System.Windows.Forms;

internal sealed class WebcamFeed : IDisposable {
 readonly object gate=new object();Process process;Bitmap latest;bool stopping;internal volatile bool Ended;
 internal static List<string> Devices(string exe){
  var devices=new List<string>();
  using(var p=new Process{StartInfo=new ProcessStartInfo(exe,"-hide_banner -list_devices true -f dshow -i dummy"){UseShellExecute=false,CreateNoWindow=true,RedirectStandardError=true}}){
   p.ErrorDataReceived+=delegate(object sender,DataReceivedEventArgs e){if(e.Data==null)return;var match=Regex.Match(e.Data,"\"([^\"]+)\" \\(video\\)");if(match.Success)devices.Add(match.Groups[1].Value);};
   p.Start();p.BeginErrorReadLine();if(!p.WaitForExit(10000)){p.Kill();p.WaitForExit();throw new Exception("Không đọc được danh sách camera.");}p.WaitForExit();
  }return devices;
 }
 internal void Start(string exe,string device){
  StartPipe(exe,"-hide_banner -loglevel error -thread_queue_size 256 -f dshow -i "+BroadcastSession.Quote("video="+device)+" -an -vf \"scale=640:360:force_original_aspect_ratio=decrease,pad=640:360:(ow-iw)/2:(oh-ih)/2,fps=15\" -pix_fmt bgr24 -f rawvideo pipe:1");
 }
 internal void StartSynthetic(string exe){StartPipe(exe,"-hide_banner -loglevel error -re -f lavfi -i testsrc2=size=640x360:rate=15 -an -pix_fmt bgr24 -f rawvideo pipe:1");}
 void StartPipe(string exe,string arguments){
  process=new Process{StartInfo=new ProcessStartInfo(exe,arguments){UseShellExecute=false,CreateNoWindow=true,RedirectStandardOutput=true,RedirectStandardError=true,RedirectStandardInput=true}};
  process.ErrorDataReceived+=delegate{};process.Start();process.BeginErrorReadLine();
  new Thread(Read){IsBackground=true,Name="DTTL webcam"}.Start();
 }
 void Read(){
  try{
   var bytes=new byte[640*360*3];var stream=process.StandardOutput.BaseStream;
   while(true){int read=0;while(read<bytes.Length){int n=stream.Read(bytes,read,bytes.Length-read);if(n==0)return;read+=n;}
    var image=new Bitmap(640,360,PixelFormat.Format24bppRgb);var data=image.LockBits(new Rectangle(0,0,640,360),ImageLockMode.WriteOnly,PixelFormat.Format24bppRgb);
    try{Marshal.Copy(bytes,0,data.Scan0,bytes.Length);}finally{image.UnlockBits(data);}
    lock(gate){if(stopping){image.Dispose();return;}if(latest!=null)latest.Dispose();latest=image;}
   }
  }catch(IOException){}catch(ObjectDisposedException){}catch(InvalidOperationException){}finally{Ended=true;}
 }
 internal Bitmap Take(){lock(gate){var image=latest;latest=null;return image;}}
 public void Dispose(){
  lock(gate){if(stopping)return;stopping=true;if(latest!=null){latest.Dispose();latest=null;}}
  var p=process;if(p!=null)ThreadPool.QueueUserWorkItem(delegate{try{if(!p.HasExited){p.Kill();p.WaitForExit(3000);}}catch(InvalidOperationException){}finally{p.Dispose();}});
 }
}
internal sealed class WebcamPicker : Form {
 internal string Encoder,Device;readonly TextBox path=new TextBox{Left=12,Top=12,Width=400};
 readonly ComboBox list=new ComboBox{Left=12,Top=55,Width=480,DropDownStyle=ComboBoxStyle.DropDownList};
 readonly Button scan=new Button{Left=420,Top=10,Width=75,Text="Chọn…"},start=new Button{Left=390,Top=96,Width=100,Text="Bật webcam",Enabled=false};
 readonly Label status=new Label{Left=12,Top=100,Width=365,Height=50,Text="Chọn ffmpeg.exe để tìm webcam trên máy."};
 internal WebcamPicker(string encoder){
  Text="Webcam";ClientSize=new Size(510,160);FormBorderStyle=FormBorderStyle.FixedDialog;MaximizeBox=false;StartPosition=FormStartPosition.CenterParent;
  path.Text=encoder;Controls.AddRange(new Control[]{path,list,scan,start,status});
  scan.Click+=delegate{using(var f=new OpenFileDialog{Filter="FFmpeg|ffmpeg.exe"})if(f.ShowDialog(this)==DialogResult.OK){path.Text=f.FileName;LoadDevices();}};
  Shown+=delegate{if(File.Exists(path.Text))LoadDevices();};
  start.Click+=delegate{Encoder=path.Text;Device=(string)list.SelectedItem;DialogResult=DialogResult.OK;};
 }
 void LoadDevices(){
  string exe=path.Text;scan.Enabled=false;path.Enabled=false;start.Enabled=false;status.Text="Đang tìm webcam…";
  ThreadPool.QueueUserWorkItem(delegate{
   List<string> devices=null;try{devices=WebcamFeed.Devices(exe);}catch{}
   try{if(IsDisposed||!IsHandleCreated)return;BeginInvoke((Action)delegate{if(IsDisposed)return;scan.Enabled=true;list.Items.Clear();if(devices!=null)foreach(var device in devices)list.Items.Add(device);if(list.Items.Count>0){list.SelectedIndex=0;start.Enabled=true;status.Text="Chọn camera rồi bấm Bật webcam.";}else status.Text="Không thấy webcam. Kiểm tra kết nối và quyền Camera trong Windows.";});}catch(InvalidOperationException){}
  });
 }
}
