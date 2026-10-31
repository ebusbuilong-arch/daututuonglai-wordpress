using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Text.RegularExpressions;
using System.Windows.Forms;

// External FFmpeg is selected explicitly; stream keys are never saved or logged.
internal sealed class BroadcastSession : IDisposable {
 Process process; SystemAudioPipe systemAudio; readonly object errorLock=new object(); string errors=""; bool stopping; public volatile bool HasFrames;
 public bool Running { get { return process!=null&&!process.HasExited; } }
 public int ExitCode { get { return process==null?-1:process.ExitCode; } }
 public string Error { get { lock(errorLock)return errors+(systemAudio!=null&&systemAudio.Error!=null?"\n"+systemAudio.Error:""); } }
 internal static string Quote(string value){
  if(value==null||value.IndexOfAny(new[]{'"','\r','\n','\0'})>=0)throw new ArgumentException("Giá trị không hợp lệ.");
  return "\""+Regex.Replace(value,@"(\\+)$","$1$1")+"\"";
 }
 internal static string Destination(string server,string key){
  Uri uri;
  if(!Uri.TryCreate(server,UriKind.Absolute,out uri)||(uri.Scheme!="rtmps"&&uri.Scheme!="rtmp")||String.IsNullOrWhiteSpace(uri.Host)||!String.IsNullOrEmpty(uri.UserInfo)||!String.IsNullOrEmpty(uri.Query)||!String.IsNullOrEmpty(uri.Fragment))throw new ArgumentException("Nhập địa chỉ RTMP/RTMPS từ nền tảng.");
  if(String.IsNullOrWhiteSpace(key)||Regex.IsMatch(key,@"[\s""\\]"))throw new ArgumentException("Khóa luồng không hợp lệ.");
  return server.TrimEnd('/')+"/"+key;
 }
 internal static string Encoding(int height){return Encoding(height,false);}
 internal static string Encoding(int height,bool mixed){
  if(height!=720&&height!=1080)throw new ArgumentException("Chọn 720p hoặc 1080p.");
  int width=height==720?1280:1920;string rate=height==720?"4000k":"8000k";
  return (mixed?" -filter_complex "+Quote("[1:a][2:a]amix=inputs=2:duration=longest:dropout_transition=0:normalize=0,alimiter=limit=0.95:level=0[a]")+" -map 0:v:0 -map "+Quote("[a]"):" -map 0:v:0 -map 1:a:0")+" -vf "+Quote("scale="+width+":"+height+":force_original_aspect_ratio=decrease,pad="+width+":"+height+":(ow-iw)/2:(oh-ih)/2,setsar=1")+" -c:v libx264 -preset veryfast -pix_fmt yuv420p -r 30 -g 60 -keyint_min 60 -sc_threshold 0 -b:v "+rate+" -maxrate "+rate+" -bufsize 16000k -c:a aac -b:a 128k -ar 48000";
 }
 internal static string Arguments(Rectangle r,int height,string destination,bool live,string audio,bool synthetic){return Arguments(r,height,destination,live,audio,synthetic,null);}
 internal static string Arguments(Rectangle r,int height,string destination,bool live,string audio,bool synthetic,SystemAudioPipe loopback){
  string input=synthetic?"-re -f lavfi -i testsrc2=size=1280x720:rate=30":"-f gdigrab -framerate 30 -draw_mouse 1 -offset_x "+r.X+" -offset_y "+r.Y+" -video_size "+r.Width+"x"+r.Height+" -i desktop";
  string sound=String.IsNullOrWhiteSpace(audio)?" -f lavfi -i anullsrc=r=48000:cl=stereo":" -f dshow -i "+Quote("audio="+audio);
  if(loopback!=null){sound=" -probesize 32 -analyzeduration 0 -f "+loopback.RawFormat+" -ar "+loopback.Format.SampleRate+" -ac "+loopback.Format.Channels+" -i "+Quote(loopback.PathName)+(String.IsNullOrWhiteSpace(audio)?"":" -f dshow -i "+Quote("audio="+audio));}
  return "-hide_banner -loglevel error -progress pipe:1 -nostats -n "+input+sound+Encoding(height,loopback!=null&&!String.IsNullOrWhiteSpace(audio))+(live?" -rw_timeout 15000000 -f flv ":" -movflags +faststart -f mp4 ")+Quote(destination);
 }
 internal void Start(string exe,Rectangle r,int height,string destination,bool live,string audio,bool synthetic){Start(exe,r,height,destination,live,audio,synthetic,false);}
 internal void Start(string exe,Rectangle r,int height,string destination,bool live,string audio,bool synthetic,bool captureSystem){
  if(Running)throw new InvalidOperationException("Đang chạy.");
  if(captureSystem)systemAudio=new SystemAudioPipe();
  process=new Process{StartInfo=new ProcessStartInfo(exe,Arguments(r,height,destination,live,audio,synthetic,systemAudio)){UseShellExecute=false,CreateNoWindow=true,RedirectStandardInput=true,RedirectStandardOutput=true,RedirectStandardError=true}};
  process.OutputDataReceived+=delegate(object sender,DataReceivedEventArgs e){if(e.Data!=null&&e.Data.StartsWith("frame=")){int frame;if(int.TryParse(e.Data.Substring(6),out frame)&&frame>0)HasFrames=true;}};
  // Local recording has no stream key. Live errors use fixed messages only, never raw URLs.
  process.ErrorDataReceived+=delegate(object sender,DataReceivedEventArgs e){if(String.IsNullOrEmpty(e.Data))return;lock(errorLock){
   if(live)errors="Không gửi được luồng. Kiểm tra kết nối, URL/khóa luồng và thiết bị âm thanh.";
   else if(errors.Length<5000)errors+=e.Data+"\n";
  }};
  process.Start();process.BeginOutputReadLine();process.BeginErrorReadLine();
 }
 public void Dispose(){
  if(stopping)return;stopping=true;
  if(process!=null){try{if(!process.HasExited){process.StandardInput.WriteLine("q");process.StandardInput.Flush();if(!process.WaitForExit(8000)){process.Kill();process.WaitForExit();}}}catch(InvalidOperationException){}catch(IOException){}finally{process.Dispose();process=null;}}
  if(systemAudio!=null){systemAudio.Dispose();systemAudio=null;}
 }
}
internal sealed class BroadcastForm : Form {
 readonly ComboBox screen=new ComboBox{DropDownStyle=ComboBoxStyle.DropDownList};
 readonly ComboBox quality=new ComboBox{DropDownStyle=ComboBoxStyle.DropDownList};
 readonly TextBox encoder=new TextBox();readonly ComboBox audio=new ComboBox{DropDownStyle=ComboBoxStyle.DropDownList};
 readonly CheckBox record=new CheckBox{Text="Lưu video MP4",Checked=true,AutoSize=true};
 readonly CheckBox[] live=new CheckBox[3];readonly TextBox[] urls=new TextBox[3],keys=new TextBox[3];
 readonly Label status=new Label{AutoSize=false,Height=70};
 readonly Button start=new Button{Text="Bắt đầu",Width=100},stop=new Button{Text="Dừng",Width=100,Enabled=false};
 readonly System.Collections.Generic.List<BroadcastSession> sessions=new System.Collections.Generic.List<BroadcastSession>();
 readonly System.Collections.Generic.List<string> names=new System.Collections.Generic.List<string>();
 readonly Timer poll=new Timer{Interval=1000};readonly Panel settings=new Panel{Dock=DockStyle.Top,Height=360};
 string savedFile; bool hasLive; string lastError=""; readonly Button details=new Button{Text="Chi tiết lỗi",Width=100,Visible=false};
 internal BroadcastForm(){
  Text="Quay / Livestream • thử nghiệm 0.6.3";Size=new Size(720,540);MinimumSize=Size;MaximumSize=Size;StartPosition=FormStartPosition.CenterScreen;Font=new Font("Segoe UI",9);FormBorderStyle=FormBorderStyle.FixedDialog;MaximizeBox=false;
  Controls.Add(settings);int y=12;
  Row("FFmpeg.exe",encoder,y);var browse=new Button{Text="Chọn…",Left=600,Top=y,Width=80};settings.Controls.Add(browse);encoder.Width=445;
  browse.Click+=delegate{using(var f=new OpenFileDialog{Filter="FFmpeg|ffmpeg.exe"})if(f.ShowDialog(this)==DialogResult.OK)encoder.Text=f.FileName;};
  string bundled=Path.Combine(AppDomain.CurrentDomain.BaseDirectory,"ffmpeg.exe");if(File.Exists(bundled))encoder.Text=bundled;
  y+=36;Row("Màn hình",screen,y);foreach(var s in Screen.AllScreens)screen.Items.Add(s.DeviceName+" • "+s.Bounds.Width+"×"+s.Bounds.Height);screen.SelectedIndex=0;
  y+=36;Row("Độ nét",quality,y);quality.Items.AddRange(new object[]{"720p / 30 fps","1080p / 30 fps"});quality.SelectedIndex=1;
  y+=36;Row("Micro",audio,y);audio.Items.Add("Tắt micro (vẫn thu tiếng máy)");audio.SelectedIndex=0;
  y+=30;settings.Controls.Add(new Label{Text="Luôn thu tiếng loa/tai nghe mặc định và trộn với micro đã chọn.",Left=150,Top=y,Width=540});
  y+=28;record.Location=new Point(150,y);settings.Controls.Add(record);
  string[] platforms={"YouTube","Facebook","TikTok"};
  for(int i=0;i<3;i++){y+=38;live[i]=new CheckBox{Text=platforms[i],Left=8,Top=y,Width=130};urls[i]=new TextBox{Left=150,Top=y,Width=320};keys[i]=new TextBox{Left=480,Top=y,Width=200,UseSystemPasswordChar=true};settings.Controls.AddRange(new Control[]{live[i],urls[i],keys[i]});}
  settings.Controls.Add(new Label{Text="Tích nơi muốn phát • cột giữa: URL máy chủ • cột phải: khóa luồng",Left=150,Top=y+28,Width=540});
  details.Location=new Point(370,370);Controls.Add(details);details.Click+=delegate{MessageBox.Show(this,lastError,"Chi tiết lỗi quay — Ctrl+C để sao chép");};
  start.Location=new Point(150,370);stop.Location=new Point(260,370);status.Location=new Point(12,410);status.Width=680;Controls.AddRange(new Control[]{start,stop,status});status.Text="Chưa quay/phát. Chọn FFmpeg và màn hình nguồn. Khóa luồng chỉ giữ trong phiên này.";
  Shown+=delegate{System.Threading.ThreadPool.QueueUserWorkItem(delegate{string[] microphones;try{microphones=NativeWebcam.Microphones();}catch{return;}try{BeginInvoke((Action)delegate{if(IsDisposed)return;audio.Items.AddRange(microphones);if(microphones.Length==1)audio.SelectedIndex=1;});}catch(InvalidOperationException){}});};
  start.Click+=delegate{StartAll();};stop.Click+=delegate{StopAll();};poll.Tick+=delegate{UpdateStatus();};
  FormClosing+=delegate{StopAll();};FormClosed+=delegate{poll.Dispose();foreach(var key in keys)key.Clear();};
 }
 void Row(string label,Control c,int y){settings.Controls.Add(new Label{Text=label,Left=8,Top=y+3,Width=140});c.SetBounds(150,y,530,26);settings.Controls.Add(c);}
 void StartAll(){
  try{
   if(!File.Exists(encoder.Text)||!String.Equals(Path.GetFileName(encoder.Text),"ffmpeg.exe",StringComparison.OrdinalIgnoreCase))throw new Exception("Chọn ffmpeg.exe từ bản Windows của FFmpeg (ffmpeg.org/download.html).");
   var monitors=Screen.AllScreens;if(screen.SelectedIndex<0||screen.SelectedIndex>=monitors.Length||!screen.Text.StartsWith(monitors[screen.SelectedIndex].DeviceName+" •"))throw new Exception("Danh sách màn hình thay đổi. Đóng rồi mở lại cửa sổ quay.");
   var targets=new System.Collections.Generic.List<string>();var labels=new System.Collections.Generic.List<string>();
   for(int i=0;i<3;i++)if(live[i].Checked){targets.Add(BroadcastSession.Destination(urls[i].Text.Trim(),keys[i].Text.Trim()));labels.Add(live[i].Text);}
   if(!record.Checked&&targets.Count==0)throw new Exception("Chọn lưu video hoặc nền tảng để phát.");
   if(targets.Count>0&&MessageBox.Show(this,"Gửi màn hình đã chọn tới "+String.Join(", ",labels.ToArray())+"?\nKhi test YouTube, đặt buổi phát Riêng tư trong YouTube Studio trước.","Bắt đầu phát",MessageBoxButtons.OKCancel)!=DialogResult.OK)return;
   hasLive=targets.Count>0;lastError="";details.Visible=false;savedFile=null;if(record.Checked){using(var f=new SaveFileDialog{Filter="Video MP4|*.mp4",FileName="DTTL-"+DateTime.Now.ToString("yyyyMMdd-HHmmss")+".mp4"}){if(f.ShowDialog(this)!=DialogResult.OK)return;if(File.Exists(f.FileName))throw new Exception("Chọn tên file mới để giữ video cũ.");savedFile=f.FileName;}}
   settings.Enabled=false;start.Enabled=false;stop.Enabled=true;
   if(savedFile!=null){targets.Insert(0,savedFile);labels.Insert(0,"Video");}
   for(int i=0;i<targets.Count;i++){var session=new BroadcastSession();sessions.Add(session);names.Add(labels[i]);session.Start(encoder.Text,monitors[screen.SelectedIndex].Bounds,quality.SelectedIndex==0?720:1080,targets[i],labels[i]!="Video",audio.SelectedIndex>0?audio.Text:"",false,true);}
   poll.Start();UpdateStatus();
  }catch(Exception ex){StopAll();lastError="Không thể bắt đầu: "+ex.Message;details.Visible=true;status.Text=lastError;MessageBox.Show(this,lastError,Text);}
 }
 void UpdateStatus(){
  var lines=new System.Collections.Generic.List<string>();bool any=false;
  for(int i=0;i<sessions.Count;i++){var s=sessions[i];bool running=s.Running;any|=running;lines.Add(names[i]+": "+(running?(s.HasFrames?"đang quay/phát":"đang khởi động…"):"đã dừng (mã "+s.ExitCode+")"));if(!running&&s.ExitCode!=0){lastError=names[i]+": "+s.Error;details.Visible=true;}}
  status.Text=String.Join(" • ",lines.ToArray())+(details.Visible?"\nBấm Chi tiết lỗi để xem nguyên nhân.":hasLive?"\nKiểm tra hình xem trước trên nền tảng để xác nhận nhận luồng.":"\nBấm Dừng để hoàn tất file MP4.");
  if(!any){poll.Stop();foreach(var s in sessions)s.Dispose();sessions.Clear();names.Clear();settings.Enabled=true;start.Enabled=true;stop.Enabled=false;}
 }
 void StopAll(){poll.Stop();foreach(var s in sessions)s.Dispose();sessions.Clear();names.Clear();settings.Enabled=true;start.Enabled=true;stop.Enabled=false;status.Text="Đã dừng."+(savedFile==null?"":" Video: "+savedFile+" — mở file để kiểm tra.");}
}
