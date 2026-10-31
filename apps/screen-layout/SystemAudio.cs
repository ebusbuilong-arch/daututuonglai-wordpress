using System;
using System.Diagnostics;
using System.IO;
using System.IO.Pipes;
using System.Threading;
using NAudio.Wave;

// One local pipe per encoder. A paced reader supplies silence when the render endpoint is idle.
internal sealed class SystemAudioPipe : IDisposable {
 readonly IWaveIn capture; readonly BufferedWaveProvider buffer; readonly NamedPipeServerStream pipe;
 readonly ManualResetEvent stop=new ManualResetEvent(false); readonly Thread worker;
 internal readonly string PathName; internal readonly WaveFormat Format; internal readonly string RawFormat;
 volatile bool disposed; internal volatile string Error;
 internal SystemAudioPipe():this(new WasapiLoopbackCapture()){}
 internal SystemAudioPipe(IWaveIn source){
  capture=source;Format=capture.WaveFormat;
  var extended=Format as WaveFormatExtensible;if(extended!=null)Format=extended.ToStandardWaveFormat();
  if(Format.Encoding==WaveFormatEncoding.IeeeFloat&&Format.BitsPerSample==32)RawFormat="f32le";
  else if(Format.Encoding==WaveFormatEncoding.Pcm&&(Format.BitsPerSample==16||Format.BitsPerSample==24||Format.BitsPerSample==32))RawFormat="s"+Format.BitsPerSample+"le";
  else {capture.Dispose();throw new InvalidOperationException("Định dạng âm thanh loa chưa được hỗ trợ.");}
  buffer=new BufferedWaveProvider(Format){BufferDuration=TimeSpan.FromSeconds(1),DiscardOnBufferOverflow=true,ReadFully=true};
  string name="DTTL-audio-"+Guid.NewGuid().ToString("N");PathName=@"\\.\pipe\"+name;
  pipe=new NamedPipeServerStream(name,PipeDirection.Out,1,PipeTransmissionMode.Byte,PipeOptions.Asynchronous,4096,65536);
  capture.DataAvailable+=Data;capture.RecordingStopped+=Stopped;
  worker=new Thread(Run){IsBackground=true,Name="DTTL system audio"};
  try{capture.StartRecording();worker.Start();}catch{capture.DataAvailable-=Data;capture.RecordingStopped-=Stopped;capture.Dispose();pipe.Dispose();stop.Dispose();throw;}
 }
 void Data(object sender,WaveInEventArgs e){if(!disposed)buffer.AddSamples(e.Buffer,0,e.BytesRecorded);}
 void Stopped(object sender,StoppedEventArgs e){if(!disposed&&e.Exception!=null){Error="Không đọc được tiếng máy. Kiểm tra thiết bị loa/tai nghe rồi bắt đầu lại.";stop.Set();try{pipe.Dispose();}catch{}}}
 void Run(){
  try{
   pipe.WaitForConnection();if(disposed)return;buffer.ClearBuffer();
   // WASAPI delivers packets in batches; retain a short cushion instead of inserting gaps between batches.
   if(stop.WaitOne(150))return;
   int frames=Math.Max(1,Format.SampleRate/50);byte[] chunk=new byte[frames*Format.BlockAlign];
   var clock=Stopwatch.StartNew();long total=0;
   while(!disposed){
    long due=total*1000/Format.SampleRate;int wait=(int)Math.Max(0,Math.Min(100,due-clock.ElapsedMilliseconds));
    if(stop.WaitOne(wait))break;
    buffer.Read(chunk,0,chunk.Length);pipe.Write(chunk,0,chunk.Length);total+=frames;
    // Do not send a burst of old audio after an encoder stall.
    if(clock.ElapsedMilliseconds-total*1000/Format.SampleRate>250){buffer.ClearBuffer();clock.Restart();total=0;}
   }
  }catch(IOException){if(!disposed&&Error==null)Error="Đường truyền âm thanh tới bộ quay đã đóng.";}
   catch(ObjectDisposedException){}
 }
 public void Dispose(){if(disposed)return;disposed=true;stop.Set();capture.DataAvailable-=Data;capture.RecordingStopped-=Stopped;
  try{capture.StopRecording();}catch{}try{capture.Dispose();}catch{}pipe.Dispose();
  if(worker.IsAlive)worker.Join(500);
  // stop stays valid if an audio driver takes longer to release its worker.
  if(!worker.IsAlive)stop.Dispose();
 }
}
