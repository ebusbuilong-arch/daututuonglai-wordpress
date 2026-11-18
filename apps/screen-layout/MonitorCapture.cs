using System;
using System.Collections.Generic;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.Threading;

internal sealed class MonitorFrame : IDisposable {
 internal int Index; internal string Source; internal Rectangle Bounds; internal Size Output; internal Bitmap Image;
 public void Dispose(){if(Image!=null){Image.Dispose();Image=null;}}
}
// One background worker, one pending request and one result batch: no capture on the UI thread.
internal sealed class MonitorCapture : IDisposable {
 readonly object gate=new object();readonly AutoResetEvent wake=new AutoResetEvent(false);
 List<MonitorFrame> pending,ready;bool stopping;
 internal MonitorCapture(){var thread=new Thread(Run){IsBackground=true,Name="DTTL monitor capture"};thread.Start();}
 internal void Request(List<MonitorFrame> request){lock(gate){if(stopping)return;pending=request;wake.Set();}}
 internal List<MonitorFrame> Take(){lock(gate){var result=ready;ready=null;return result;}}
 static void Free(List<MonitorFrame> list){if(list!=null)foreach(var f in list)f.Dispose();}
 void Run(){
  var buffers=new Dictionary<string,Bitmap>();
  try{while(true){
   wake.WaitOne();List<MonitorFrame> work;
   lock(gate){if(stopping)break;work=pending;pending=null;}
   if(work==null)continue;
   foreach(var f in work){
    if(f.Bounds.Width<=0||f.Bounds.Height<=0||f.Bounds.Width>4096||f.Bounds.Height>4096)continue;
    try{
     Bitmap full;if(!buffers.TryGetValue(f.Source,out full)||full.Size!=f.Bounds.Size){if(full!=null)full.Dispose();full=new Bitmap(f.Bounds.Width,f.Bounds.Height);buffers[f.Source]=full;}
     using(var g=Graphics.FromImage(full))g.CopyFromScreen(f.Bounds.Location,Point.Empty,f.Bounds.Size,CopyPixelOperation.SourceCopy);
     f.Image=new Bitmap(Math.Max(1,f.Output.Width),Math.Max(1,f.Output.Height));
     using(var g=Graphics.FromImage(f.Image)){g.InterpolationMode=InterpolationMode.Bilinear;g.DrawImage(full,new Rectangle(Point.Empty,f.Image.Size));}
    }catch(System.ComponentModel.Win32Exception){f.Dispose();}catch(System.Runtime.InteropServices.ExternalException){f.Dispose();}catch(ArgumentException){f.Dispose();}
   }
   var unused=new List<string>();foreach(var key in buffers.Keys){bool used=false;foreach(var f in work)if(f.Source==key){used=true;break;}if(!used)unused.Add(key);}foreach(var key in unused){buffers[key].Dispose();buffers.Remove(key);}
   lock(gate){if(stopping){Free(work);break;}Free(ready);ready=work;}
  }}finally{foreach(var b in buffers.Values)b.Dispose();wake.Dispose();}
 }
 public void Dispose(){lock(gate){if(stopping)return;stopping=true;pending=null;Free(ready);ready=null;wake.Set();}}
}
