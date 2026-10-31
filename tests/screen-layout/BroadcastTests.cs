using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Threading;
using System.Windows.Forms;
class BroadcastTests {
 sealed class FakeCamera : AForge.Video.IVideoSource {
  public event AForge.Video.NewFrameEventHandler NewFrame;
  public event AForge.Video.VideoSourceErrorEventHandler VideoSourceError;
  public event AForge.Video.PlayingFinishedEventHandler PlayingFinished;
  public string Source{get{return "synthetic";}}public int FramesReceived{get{return 1;}}public long BytesReceived{get{return 0;}}public bool IsRunning{get;private set;}
  public void Start(){IsRunning=true;}public void SignalToStop(){IsRunning=false;}public void WaitForStop(){}public void Stop(){SignalToStop();}
  public void Emit(Bitmap b){if(NewFrame!=null)NewFrame(this,new AForge.Video.NewFrameEventArgs(b));}
 }
 sealed class FakeAudio : NAudio.Wave.IWaveIn {
  public event EventHandler<NAudio.Wave.WaveInEventArgs> DataAvailable;
  public event EventHandler<NAudio.Wave.StoppedEventArgs> RecordingStopped;
  public NAudio.Wave.WaveFormat WaveFormat{get;set;}
  System.Threading.Timer timer; int frame; internal bool Disposed;
  internal FakeAudio(){WaveFormat=new NAudio.Wave.WaveFormat(48000,16,2);}
  public void StartRecording(){var clock=Stopwatch.StartNew();timer=new System.Threading.Timer(delegate{
   int frames=Math.Max(0,(int)(clock.ElapsedMilliseconds*48)-frame);byte[] data=new byte[frames*4];for(int i=0;i<frames;i++){short sample=frame++<24000?(short)0:(short)(Math.Sin(2*Math.PI*440*frame/48000)*5000);for(int channel=0;channel<2;channel++){int at=i*4+channel*2;data[at]=(byte)sample;data[at+1]=(byte)(sample>>8);}}
   var handler=DataAvailable;if(handler!=null)handler(this,new NAudio.Wave.WaveInEventArgs(data,data.Length));
  },null,0,20);}
  public void StopRecording(){if(timer!=null){timer.Dispose();timer=null;}}
  public void Dispose(){StopRecording();Disposed=true;}
 }
 static void RunEncoder(string ff,string arguments){
  using(var p=Process.Start(new ProcessStartInfo(ff,arguments){UseShellExecute=false,RedirectStandardError=true,RedirectStandardOutput=true,CreateNoWindow=true})){
   string error="";p.ErrorDataReceived+=delegate(object sender,DataReceivedEventArgs e){error+=e.Data+"\n";};p.OutputDataReceived+=delegate{};p.BeginErrorReadLine();p.BeginOutputReadLine();
   if(!p.WaitForExit(30000)){p.Kill();throw new Exception("Audio encoding timeout");}p.WaitForExit();if(p.ExitCode!=0)throw new Exception(error);
  }
 }
 static double Tone(byte[] data,int hz){
  double re=0,im=0;int start=48000,count=Math.Min(48000,data.Length/4-start);
  for(int i=0;i<count;i++){float value=BitConverter.ToSingle(data,(i+start)*4);re+=value*Math.Cos(2*Math.PI*hz*i/48000);im+=value*Math.Sin(2*Math.PI*hz*i/48000);}
  return Math.Sqrt(re*re+im*im)/Math.Max(1,count);
 }
 static void AudioTests(string ff,string folder){
  var fake=new FakeAudio();string output=Path.Combine(folder,"loopback-pipe.mp4");if(File.Exists(output))File.Delete(output);
  using(var pipe=new SystemAudioPipe(fake)){
   // Exercise the exact generated input command, including silence before playback begins.
   string command=BroadcastSession.Arguments(Rectangle.Empty,720,output,false,"",true,pipe);
   command=command.Replace(" -movflags", " -t 3 -movflags");RunEncoder(ff,command);
  }
  if(!fake.Disposed)throw new Exception("Loopback source not disposed");
  string raw=Path.Combine(folder,"loopback.f32");if(File.Exists(raw))File.Delete(raw);
  RunEncoder(ff,"-v error -i "+BroadcastSession.Quote(output)+" -map 0:a:0 -ac 1 -ar 48000 -f f32le "+BroadcastSession.Quote(raw));
  double amplitude=Tone(File.ReadAllBytes(raw),440);Console.WriteLine("Loopback amplitude: "+amplitude);if(amplitude<.02)throw new Exception("Loopback tone missing");
  Console.WriteLine("PASS paced audio pipe: initial silence then tone captured in MP4; source disposed");
  string mixed=Path.Combine(folder,"mixed.mp4");if(File.Exists(mixed))File.Delete(mixed);
  RunEncoder(ff,"-v error -f lavfi -i testsrc2=size=1280x720:rate=30 -f lavfi -i sine=frequency=440:sample_rate=48000 -f lavfi -i sine=frequency=880:sample_rate=48000"+BroadcastSession.Encoding(720,true)+" -t 3 -f mp4 "+BroadcastSession.Quote(mixed));
  File.Delete(raw);RunEncoder(ff,"-v error -i "+BroadcastSession.Quote(mixed)+" -map 0:a:0 -ac 1 -ar 48000 -f f32le "+BroadcastSession.Quote(raw));
  byte[] samples=File.ReadAllBytes(raw);if(Tone(samples,440)<.02||Tone(samples,880)<.02)throw new Exception("Both mixed sources must remain audible");
  Console.WriteLine("PASS two independent tones survive mixed AAC output (system + microphone filter)");
  using(var bad=new BroadcastSession()){bad.Start(ff,Rectangle.Empty,720,Path.Combine(folder,"missing-folder","bad.mp4"),false,"",true);for(int i=0;i<150&&bad.Running;i++)Thread.Sleep(100);if(bad.Running||bad.ExitCode==0)throw new Exception("Invalid output should fail");Thread.Sleep(100);if(String.IsNullOrEmpty(bad.Error))throw new Exception("Recording error detail lost");}
  Console.WriteLine("PASS failed recording exposes local encoder error");
 }
 static void CheckVideo(string probe,string file,int height){
  var p=Process.Start(new ProcessStartInfo(probe,"-v error -select_streams v:0 -show_entries stream=width,height,codec_name -of csv=p=0 "+BroadcastSession.Quote(file)){UseShellExecute=false,RedirectStandardOutput=true,CreateNoWindow=true});
  string result=p.StandardOutput.ReadToEnd();p.WaitForExit();if(p.ExitCode!=0||!result.Contains("h264,"+(height==720?1280:1920)+","+height))throw new Exception("Bad video "+result);p.Dispose();
 }
 static void AwaitFrames(BroadcastSession s){for(int i=0;i<200&&!s.HasFrames&&s.Running;i++)Thread.Sleep(100);if(!s.HasFrames)throw new Exception("Encoder produced no frames");Thread.Sleep(2000);}
 [STAThread] static void Main(string[] args){
  string ff=args[0],probe=Path.Combine(Path.GetDirectoryName(ff),"ffprobe.exe"),folder=args[1];
  try{Native.SetProcessDpiAwarenessContext(new IntPtr(-4));}catch{}
  var fake=new FakeCamera();using(var native=new NativeWebcam(fake)){
   using(var original=new Bitmap(1280,720)){fake.Emit(original);using(var frame=native.Take()){if(frame==null||frame.Size!=original.Size)throw new Exception("Native frame missing");}}
   native.Dispose();if(fake.IsRunning)throw new Exception("Native camera did not receive stop");using(var late=new Bitmap(10,10))fake.Emit(late);if(native.Take()!=null)throw new Exception("Camera accepted late frame");
  }
  Console.WriteLine("PASS native camera frame ownership and stop without FFmpeg; simulated camera only");
  using(var webcam=new WebcamFeed()){
   webcam.StartSynthetic(ff);Bitmap image=null;for(int attempt=0;attempt<100&&image==null;attempt++){Thread.Sleep(100);image=webcam.Take();}
   if(image==null||image.Size!=new Size(640,360))throw new Exception("Webcam frame decoding failed");image.Dispose();webcam.Dispose();
   for(int attempt=0;attempt<50&&!webcam.Ended;attempt++)Thread.Sleep(100);if(!webcam.Ended)throw new Exception("Camera process did not stop");
   Console.WriteLine("PASS webcam raw-frame pipeline and stop using synthetic input; no physical camera tested");
  }
  foreach(string bad in new[]{"https://example.com","file:///tmp/test","rtmps://user:secret@example.com/live"}){bool rejected=false;try{BroadcastSession.Destination(bad,"key");}catch(ArgumentException){rejected=true;}if(!rejected)throw new Exception("Invalid destination accepted");}
  AudioTests(ff,folder);
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
