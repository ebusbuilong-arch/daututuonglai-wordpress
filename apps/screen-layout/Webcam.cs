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
 readonly object gate=new object();Process process;Bitmap latest;volatile bool stopping;bool synced;Thread syncWorker;internal volatile bool Ended;
 internal static List<string> Devices(string exe){
  var devices=new List<string>();
  using(var p=new Process{StartInfo=new ProcessStartInfo(exe,"-hide_banner -list_devices true -f dshow -i dummy"){UseShellExecute=false,CreateNoWindow=true,RedirectStandardError=true}}){
   p.ErrorDataReceived+=delegate(object sender,DataReceivedEventArgs e){if(e.Data==null)return;var match=Regex.Match(e.Data,"\"([^\"]+)\" \\(video\\)");if(match.Success)devices.Add(match.Groups[1].Value);};
   p.Start();p.BeginErrorReadLine();if(!p.WaitForExit(10000)){p.Kill();p.WaitForExit();throw new Exception("Không đọc được danh sách camera.");}p.WaitForExit();
  }return devices;
 }
 internal void Start(string exe,string device){
  StartPipe(exe,"-hide_banner -loglevel error -f dshow -i "+BroadcastSession.Quote("video="+device)+" -an -vf \"scale=640:360:force_original_aspect_ratio=decrease,pad=640:360:(ow-iw)/2:(oh-ih)/2,fps=15\" -pix_fmt bgr24 -f rawvideo pipe:1");
 }
 internal void StartVideo(string exe,string path){
  if(!File.Exists(path))throw new FileNotFoundException("Không tìm thấy video AI.");
  StartPipe(exe,SilentVideoArguments(path,true));
 }
 // MP4 audio and frames share the narration playhead. The video decoder NEVER outputs audio.
 internal void StartSyncedVideo(string exe,string path,NarrationAudio voice){
  if(!File.Exists(path))throw new FileNotFoundException("Không tìm thấy MP4 có lời.");
  synced=true;syncWorker=new Thread(delegate(){ReadSynced(exe,path,voice);}){IsBackground=true,Name="DTTL MP4 synced"};syncWorker.Start();
 }
 internal static string SilentVideoArguments(string path,bool loop){
  return "-hide_banner -loglevel error "+(loop?"-re -stream_loop -1 ":"")+"-i "+BroadcastSession.Quote(path)+" -map 0:v:0 -an -vf "+BroadcastSession.Quote("setpts=PTS-STARTPTS,scale=640:360:force_original_aspect_ratio=decrease,pad=640:360:(ow-iw)/2:(oh-ih)/2,fps=15")+" -pix_fmt bgr24 -f rawvideo pipe:1";
 }
 void ReadSynced(string exe,string path,NarrationAudio voice){
  try{while(!stopping){
   int version=voice.PlaybackVersion;long index=0;
   var p=new Process{StartInfo=new ProcessStartInfo(exe,SilentVideoArguments(path,false)){UseShellExecute=false,CreateNoWindow=true,RedirectStandardOutput=true,RedirectStandardError=true}};
   try{
    p.ErrorDataReceived+=delegate{};
    lock(gate){if(stopping)return;process=p;p.Start();p.BeginErrorReadLine();}
    byte[] bytes=new byte[640*360*3];var stream=p.StandardOutput.BaseStream;
    while(!stopping&&voice.PlaybackVersion==version){
     int read=0;while(read<bytes.Length&&!stopping){int n=stream.Read(bytes,read,bytes.Length-read);if(n==0)break;read+=n;}
     if(read<bytes.Length){
      if(!stopping){p.WaitForExit();if(p.ExitCode!=0||index==0)throw new IOException("Không đọc được hình MP4.");}
      // Keep the final frame, including after the audio ends; replay starts only on user request.
      while(!stopping&&voice.PlaybackVersion==version)Thread.Sleep(10);
      break;
     }
     double due=index/15.0;
     while(!stopping&&voice.PlaybackVersion==version&&Math.Max(0,voice.PositionSeconds-0.15)<due)Thread.Sleep(5);
     if(stopping||voice.PlaybackVersion!=version)break;
     var image=new Bitmap(640,360,PixelFormat.Format24bppRgb);var data=image.LockBits(new Rectangle(0,0,640,360),ImageLockMode.WriteOnly,PixelFormat.Format24bppRgb);
     try{Marshal.Copy(bytes,0,data.Scan0,bytes.Length);}finally{image.UnlockBits(data);}
     lock(gate){if(stopping){image.Dispose();break;}if(latest!=null)latest.Dispose();latest=image;}index++;
    }
   }finally{
    lock(gate){if(process==p)process=null;try{if(!p.HasExited){p.Kill();p.WaitForExit(3000);}}catch(InvalidOperationException){}}
    p.Dispose();
   }
  }}catch(Exception){/* surfaced by cameraTimer; never crash the UI from decoder worker */}finally{Ended=true;}
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
  if(synced){lock(gate){try{if(process!=null&&!process.HasExited)process.Kill();}catch(InvalidOperationException){}}if(syncWorker!=null)syncWorker.Join(1000);return;}
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
