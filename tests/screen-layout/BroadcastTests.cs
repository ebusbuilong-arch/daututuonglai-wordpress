using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Threading;
using System.Windows.Forms;
class BroadcastTests {
 static void CheckVideo(string probe,string file,int height){
  var p=Process.Start(new ProcessStartInfo(probe,"-v error -select_streams v:0 -show_entries stream=width,height,codec_name -of csv=p=0 "+BroadcastSession.Quote(file)){UseShellExecute=false,RedirectStandardOutput=true,CreateNoWindow=true});
  string result=p.StandardOutput.ReadToEnd();p.WaitForExit();if(p.ExitCode!=0||!result.Contains("h264,"+(height==720?1280:1920)+","+height))throw new Exception("Bad video "+result);p.Dispose();
 }
 static void AwaitFrames(BroadcastSession s){for(int i=0;i<200&&!s.HasFrames&&s.Running;i++)Thread.Sleep(100);if(!s.HasFrames)throw new Exception("Encoder produced no frames");Thread.Sleep(2000);}
 [STAThread] static void Main(string[] args){
  string ff=args[0],probe=Path.Combine(Path.GetDirectoryName(ff),"ffprobe.exe"),folder=args[1];
  try{Native.SetProcessDpiAwarenessContext(new IntPtr(-4));}catch{}
  foreach(string bad in new[]{"https://example.com","file:///tmp/test","rtmps://user:secret@example.com/live"}){bool rejected=false;try{BroadcastSession.Destination(bad,"key");}catch(ArgumentException){rejected=true;}if(!rejected)throw new Exception("Invalid destination accepted");}
  foreach(int height in new[]{720,1080}){
   string file=Path.Combine(folder,"record-"+height+".mp4");if(File.Exists(file))File.Delete(file);
   using(var s=new BroadcastSession()){s.Start(ff,Screen.PrimaryScreen.Bounds,height,file,false,"",false);AwaitFrames(s);}
   CheckVideo(probe,file,height);Console.WriteLine("PASS desktop capture H264/AAC MP4 "+height+"p and graceful stop");
  }
  string received=Path.Combine(folder,"rtmp-received.flv");if(File.Exists(received))File.Delete(received);
  using(var receiver=new Process{StartInfo=new ProcessStartInfo(ff,"-hide_banner -loglevel error -listen 1 -timeout 15000000 -i rtmp://127.0.0.1:19350/live/test -c copy -f flv "+BroadcastSession.Quote(received)){UseShellExecute=false,CreateNoWindow=true,RedirectStandardError=true}}){
   receiver.ErrorDataReceived+=delegate{};receiver.Start();receiver.BeginErrorReadLine();Thread.Sleep(1000);
   try{using(var s=new BroadcastSession()){s.Start(ff,Rectangle.Empty,720,"rtmp://127.0.0.1:19350/live/test",true,"",true);AwaitFrames(s);}if(!receiver.WaitForExit(10000))throw new Exception("Receiver timeout");CheckVideo(probe,received,720);Console.WriteLine("PASS local RTMP send/receive with H264 720p; NOT a YouTube end-to-end test");}
   finally{if(!receiver.HasExited){receiver.Kill();receiver.WaitForExit();}}
  }
  Console.WriteLine("LIMITATION: no real YouTube/Facebook/TikTok credentials; platform reception and microphone require device testing.");
 }
}
