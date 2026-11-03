using System;
using System.Collections.Generic;
using System.Drawing;
using System.Threading;
using AForge.Video;
using AForge.Video.DirectShow;

// DirectShow camera preview; no FFmpeg process and no microphone capture until live starts.
internal sealed class NativeWebcam : IDisposable {
 readonly object gate=new object();readonly IVideoSource source;Bitmap latest;bool stopped;DateTime last=DateTime.MinValue;Timer timeout;
 internal volatile bool Ended;internal string Error;
 internal static NativeWebcam OpenDefault(){
  var devices=new FilterInfoCollection(FilterCategory.VideoInputDevice);
  if(devices.Count==0)throw new InvalidOperationException("Không thấy webcam. Cắm camera và cho phép ứng dụng desktop truy cập Camera trong Windows.");
  var device=new VideoCaptureDevice(devices[0].MonikerString);
  VideoCapabilities best=null;long score=long.MaxValue;
  foreach(var mode in device.VideoCapabilities){long value=Math.Abs((long)mode.FrameSize.Width*mode.FrameSize.Height-1280*720);if(value<score){best=mode;score=value;}}
  if(best!=null)device.VideoResolution=best;
  return new NativeWebcam(device);
 }
 internal static string[] Microphones(){var result=new List<string>();foreach(FilterInfo info in new FilterInfoCollection(FilterCategory.AudioInputDevice))result.Add(info.Name);return result.ToArray();}
 internal NativeWebcam(IVideoSource input){
  source=input;source.NewFrame+=Frame;source.VideoSourceError+=Failed;source.PlayingFinished+=Finished;
  timeout=new Timer(delegate{lock(gate){if(stopped||last!=DateTime.MinValue)return;Error="Camera chưa gửi hình. Kiểm tra quyền Camera hoặc đóng ứng dụng đang giữ camera.";Ended=true;}},null,15000,Timeout.Infinite);
  try{source.Start();}catch{Dispose();throw;}
 }
 void Frame(object sender,NewFrameEventArgs e){lock(gate){if(stopped||(DateTime.UtcNow-last).TotalMilliseconds<60)return;last=DateTime.UtcNow;var copy=(Bitmap)e.Frame.Clone();if(latest!=null)latest.Dispose();latest=copy;}}
 void Failed(object sender,VideoSourceErrorEventArgs e){Error="Không nhận được hình camera. Kiểm tra kết nối và quyền Camera của Windows.";Ended=true;}
 void Finished(object sender,ReasonToFinishPlaying reason){Ended=true;}
 internal Bitmap Take(){lock(gate){var result=latest;latest=null;return result;}}
 public void Dispose(){
  lock(gate){if(stopped)return;stopped=true;if(latest!=null){latest.Dispose();latest=null;}if(timeout!=null)timeout.Dispose();}
  source.NewFrame-=Frame;source.VideoSourceError-=Failed;source.PlayingFinished-=Finished;
  source.SignalToStop();ThreadPool.QueueUserWorkItem(delegate{try{source.WaitForStop();}finally{Ended=true;}});
 }
}
