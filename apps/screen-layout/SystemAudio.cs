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

// A single MP3 playhead fans out to every encoder; it never opens a recording device.
internal sealed class NarrationAudio : IDisposable {
 readonly object gate=new object();readonly AudioFileReader reader;readonly Timer timer;
 readonly System.Collections.Generic.List<Source> sources=new System.Collections.Generic.List<Source>();
 readonly BufferedWaveProvider monitorBuffer;WaveOutEvent monitor;bool disposed,playing,reachedEnd;readonly Stopwatch clock=Stopwatch.StartNew();long emitted;internal string Error;
 int playbackVersion;
 internal double PositionSeconds{get{lock(gate)return reader.CurrentTime.TotalSeconds;}}
 internal int PlaybackVersion{get{lock(gate)return playbackVersion;}}
 internal bool Playing{get{lock(gate)return playing;}}
 internal bool Ended{get{lock(gate)return reachedEnd||reader.Position>=reader.Length;}}
 internal NarrationAudio(string path,bool listen){
  reader=new AudioFileReader(path);
  try{if(listen){monitorBuffer=new BufferedWaveProvider(reader.WaveFormat){BufferDuration=TimeSpan.FromSeconds(1),DiscardOnBufferOverflow=true,ReadFully=true};monitor=new WaveOutEvent();monitor.Init(monitorBuffer);monitor.Play();}
   timer=new Timer(Tick,null,0,20);
  }catch{if(monitor!=null)monitor.Dispose();reader.Dispose();throw;}
 }
 internal IWaveIn CreateSource(){lock(gate){if(disposed)throw new ObjectDisposedException("NarrationAudio");return new Source(this,reader.WaveFormat);}}
 internal void Play(){lock(gate){if(disposed)return;if(reachedEnd||reader.Position>=reader.Length){reader.Position=0;reachedEnd=false;playbackVersion++;}playing=true;}}
 internal void Pause(){lock(gate){playing=false;if(monitorBuffer!=null)monitorBuffer.ClearBuffer();}}
 internal void Rewind(){lock(gate){playing=false;reader.Position=0;reachedEnd=false;playbackVersion++;if(monitorBuffer!=null)monitorBuffer.ClearBuffer();}}
 void Tick(object state){lock(gate){if(disposed)return;long due=clock.ElapsedMilliseconds*reader.WaveFormat.SampleRate/1000;int frames=(int)Math.Min(reader.WaveFormat.SampleRate/4,Math.Max(0,due-emitted));emitted=due;if(frames==0)return;byte[] data=new byte[frames*reader.WaveFormat.BlockAlign];
  try{
  if(playing){int n=reader.Read(data,0,data.Length);if(n<data.Length){playing=false;reachedEnd=true;}}
  foreach(var source in sources.ToArray())source.Emit(data);
  if(monitorBuffer!=null)monitorBuffer.AddSamples(data,0,data.Length);
  }catch(Exception ex){playing=false;Error="Không đọc được âm thanh: "+ex.Message;}
 }}
 public void Dispose(){lock(gate){if(disposed)return;disposed=true;timer.Dispose();sources.Clear();if(monitor!=null){monitor.Stop();monitor.Dispose();}reader.Dispose();}}
 sealed class Source : IWaveIn {
  readonly NarrationAudio owner;public WaveFormat WaveFormat{get;set;}
  public event EventHandler<WaveInEventArgs> DataAvailable;
  public event EventHandler<StoppedEventArgs> RecordingStopped;
  internal Source(NarrationAudio owner,WaveFormat format){this.owner=owner;WaveFormat=format;}
  public void StartRecording(){lock(owner.gate){if(!owner.sources.Contains(this))owner.sources.Add(this);}}
  public void StopRecording(){lock(owner.gate)owner.sources.Remove(this);}
  internal void Emit(byte[] data){var h=DataAvailable;if(h!=null)h(this,new WaveInEventArgs(data,data.Length));}
  public void Dispose(){StopRecording();}
 }
}
