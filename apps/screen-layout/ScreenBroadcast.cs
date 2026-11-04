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
  Start(exe,r,height,destination,live,audio,synthetic,captureSystem,null);
 }
 internal void Start(string exe,Rectangle r,int height,string destination,bool live,string audio,bool synthetic,bool captureSystem,NarrationAudio narration){
  if(Running)throw new InvalidOperationException("Đang chạy.");
  if(narration!=null){audio="";systemAudio=new SystemAudioPipe(narration.CreateSource());}
  else if(captureSystem)systemAudio=new SystemAudioPipe();
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
 readonly ComboBox mode=new ComboBox{DropDownStyle=ComboBoxStyle.DropDownList};
 readonly TextBox mp3=new TextBox(),avatar=new TextBox(),spokenVideo=new TextBox();
 readonly Panel photoSettings=new Panel{Height=108}; readonly ComboBox character=new ComboBox{DropDownStyle=ComboBoxStyle.DropDownList},shirt=new ComboBox{DropDownStyle=ComboBoxStyle.DropDownList}; readonly TextBox photoMp3=new TextBox(); readonly CheckBox photoListen=new CheckBox{Text="Nghe MP3 trên máy",AutoSize=true};
 internal Action<int,int,NarrationAudio> ShowPhoto;
 readonly Panel videoSettings=new Panel{Height=108};
 readonly CheckBox listenVideo=new CheckBox{Text="Nghe tiếng video trên máy",AutoSize=true};
 readonly CheckBox systemSound=new CheckBox{Text="Thu tiếng máy",Checked=true,AutoSize=true},listen=new CheckBox{Text="Nghe MP3 trên máy",AutoSize=true};
 readonly Button playVoice=new Button{Text="Phát lời",Width=100,Enabled=false},pauseVoice=new Button{Text="Tạm dừng lời",Width=150,Enabled=false},rewindVoice=new Button{Text="Về đầu",Width=100,Enabled=false};
 readonly Panel aiSettings=new Panel{Height=108},normalSettings=new Panel{Height=108};
 NarrationAudio narration;internal Action<string,string> ShowAvatar;internal Action StopAvatar;internal Action<string,string,NarrationAudio> ShowSpokenVideo;
 readonly ComboBox screen=new ComboBox{DropDownStyle=ComboBoxStyle.DropDownList};
 readonly ComboBox quality=new ComboBox{DropDownStyle=ComboBoxStyle.DropDownList};
 readonly TextBox encoder=new TextBox();readonly ComboBox audio=new ComboBox{DropDownStyle=ComboBoxStyle.DropDownList};
 readonly CheckBox record=new CheckBox{Text="Lưu video MP4",Checked=true,AutoSize=true};
 readonly CheckBox[] live=new CheckBox[3];readonly TextBox[] urls=new TextBox[3],keys=new TextBox[3];
 readonly Label status=new Label{AutoSize=false,Height=70};
 readonly Button start=new Button{Text="Bắt đầu",Width=100},stop=new Button{Text="Dừng",Width=100,Enabled=false};
 readonly System.Collections.Generic.List<BroadcastSession> sessions=new System.Collections.Generic.List<BroadcastSession>();
 readonly System.Collections.Generic.List<string> names=new System.Collections.Generic.List<string>();
 readonly Timer prepareTimer=new Timer{Interval=100}; readonly Stopwatch prepareClock=new Stopwatch();
 Action pendingCapture; int preparationSeconds;
 readonly ComboBox prepareSeconds=new ComboBox{DropDownStyle=ComboBoxStyle.DropDownList};
 readonly Timer poll=new Timer{Interval=1000};readonly Panel settings=new Panel{Dock=DockStyle.Top,Height=440};
 string savedFile; bool hasLive; string lastError=""; readonly Button details=new Button{Text="Chi tiết lỗi",Width=100,Visible=false};
 internal BroadcastForm(){
  try{Icon=Icon.ExtractAssociatedIcon(Application.ExecutablePath);}catch{}
  Text="Phần mềm livestream màn hình • v1.7.1";AutoScaleMode=AutoScaleMode.None;ClientSize=new Size(720,700);MinimumSize=Size;MaximumSize=Size;StartPosition=FormStartPosition.CenterScreen;Font=new Font("Segoe UI",9);FormBorderStyle=FormBorderStyle.FixedDialog;MaximizeBox=false;
  BackColor=Color.FromArgb(243,247,248);ForeColor=Color.FromArgb(25,45,53);Controls.Add(settings);int y=16;
  Row("FFmpeg.exe",encoder,y);var browse=new Button{Text="Chọn…",Left=600,Top=y,Width=80,Height=30};settings.Controls.Add(browse);encoder.Width=445;
  browse.Click+=delegate{using(var f=new OpenFileDialog{Filter="FFmpeg|ffmpeg.exe"})if(f.ShowDialog(this)==DialogResult.OK)encoder.Text=f.FileName;};
  string bundled=Path.Combine(AppDomain.CurrentDomain.BaseDirectory,"ffmpeg.exe");if(File.Exists(bundled))encoder.Text=bundled;
  y+=36;Row("Màn hình",screen,y);foreach(var s in Screen.AllScreens)screen.Items.Add(s.DeviceName+" • "+s.Bounds.Width+"×"+s.Bounds.Height);screen.SelectedIndex=0;
  y+=36;Row("Độ nét",quality,y);quality.Items.AddRange(new object[]{"720p / 30 fps","1080p / 30 fps"});quality.SelectedIndex=1;
  y+=36;Row("Chế độ",mode,y);mode.Items.AddRange(new object[]{"Thường — webcam và micro","AI — video và lời MP3","MP4 có lời — hình và tiếng cùng video","Ảnh nói + MP3 — 20 mẫu"});mode.SelectedIndex=0;
  y+=36;normalSettings.SetBounds(0,y,700,108);aiSettings.SetBounds(0,y,700,108);videoSettings.SetBounds(0,y,700,108);photoSettings.SetBounds(0,y,700,108);settings.Controls.AddRange(new Control[]{normalSettings,aiSettings,videoSettings,photoSettings});
  character.Items.AddRange(new object[]{"Mẫu cũ · Tóc dài","Mẫu cũ · Tóc bob","Mẫu cũ · Tóc đuôi ngựa","Mẫu mới · Tóc dài","Mẫu mới · Tóc bob"});
  character.SelectedIndexChanged+=delegate{SetPhotoLooks();};character.SelectedIndex=0;
  character.SetBounds(150,0,250,26);shirt.SetBounds(410,0,274,26);photoSettings.Controls.AddRange(new Control[]{character,shirt,new Label{Text="Kiểu tóc / phối cảnh",Left=16,Top=4,Width=130}});AddMediaRow(photoSettings,"Lời MP3",photoMp3,36,"Âm thanh MP3|*.mp3");photoListen.Location=new Point(150,78);photoSettings.Controls.Add(photoListen);
  normalSettings.Controls.Add(new Label{Text="Micro",Left=16,Top=0,Width=128,Height=30,TextAlign=ContentAlignment.MiddleLeft});audio.SetBounds(150,0,530,26);normalSettings.Controls.Add(audio);audio.Items.Add("Tắt micro");audio.SelectedIndex=0;
  systemSound.Location=new Point(150,36);normalSettings.Controls.Add(systemSound);
  normalSettings.Controls.Add(new Label{Text="Webcam bật/tắt tại vùng phụ. Tiếng máy và micro chọn riêng.",Left=150,Top=66,Width=530,Height=28});
  AddMediaRow("Lời MP3",mp3,0,"Âm thanh MP3|*.mp3");AddMediaRow("Video AI",avatar,36,"Video MP4|*.mp4");listen.Location=new Point(150,76);aiSettings.Controls.Add(listen);
  AddMediaRow(videoSettings,"MP4 có lời",spokenVideo,0,"Video MP4|*.mp4");listenVideo.Location=new Point(150,40);videoSettings.Controls.Add(listenVideo);
  videoSettings.Controls.Add(new Label{Text="Phát hình và tiếng cùng nhau; không thu micro hoặc tiếng máy.",Left=150,Top=76,Width=530,Height=28});
  mode.SelectedIndexChanged+=delegate{SetMode();};SetMode();
  y+=108;record.Location=new Point(150,y);settings.Controls.Add(record);
  string[] platforms={"YouTube","Facebook","TikTok"};
  for(int i=0;i<3;i++){y+=38;live[i]=new CheckBox{Text=platforms[i],Left=8,Top=y,Width=130};urls[i]=new TextBox{Left=150,Top=y,Width=320};keys[i]=new TextBox{Left=480,Top=y,Width=200,UseSystemPasswordChar=true};settings.Controls.AddRange(new Control[]{live[i],urls[i],keys[i]});}
  settings.Controls.Add(new Label{Text="Tích nơi muốn phát • cột giữa: URL máy chủ • cột phải: khóa luồng",Left=150,Top=y+28,Width=540,Height=28});
  details.Location=new Point(390,462);Controls.Add(details);details.Click+=delegate{MessageBox.Show(this,lastError,"Chi tiết lỗi quay — Ctrl+C để sao chép");};
  start.Location=new Point(150,462);stop.Location=new Point(270,462);status.Location=new Point(16,562);status.Width=680;Controls.AddRange(new Control[]{start,stop,status});status.Text="Chưa quay/phát. Chọn FFmpeg và màn hình nguồn. Khóa luồng chỉ giữ trong phiên này.";
  playVoice.Location=new Point(150,510);pauseVoice.Location=new Point(270,510);rewindVoice.Location=new Point(440,510);Controls.AddRange(new Control[]{playVoice,pauseVoice,rewindVoice});
  playVoice.Click+=delegate{PlayNarration();};pauseVoice.Click+=delegate{PauseNarration();};rewindVoice.Click+=delegate{if(narration!=null)narration.Rewind();};
  prepareSeconds.Items.AddRange(new object[]{"Không chờ","3 giây","5 giây"});prepareSeconds.SelectedIndex=1;prepareSeconds.SetBounds(150,640,130,26);Controls.Add(prepareSeconds);Controls.Add(new Label{Text="Chờ trước khi quay",Left=16,Top=644,Width=130});
  Controls.Add(new Label{Text="Tự thu nhỏ bảng điều khiển khi đếm ngược.",Left=294,Top=644,Width=390});
  prepareTimer.Tick+=delegate{AdvancePreparation();};
  StyleButtons(this);
  Shown+=delegate{System.Threading.ThreadPool.QueueUserWorkItem(delegate{string[] microphones;try{microphones=NativeWebcam.Microphones();}catch{return;}try{BeginInvoke((Action)delegate{if(IsDisposed)return;audio.Items.AddRange(microphones);if(microphones.Length==1)audio.SelectedIndex=1;});}catch(InvalidOperationException){}});};
  start.Click+=delegate{StartAll();};stop.Click+=delegate{StopAll();};poll.Tick+=delegate{UpdateStatus();};
  FormClosing+=delegate{StopAll();};FormClosed+=delegate{prepareTimer.Dispose();poll.Dispose();foreach(var key in keys)key.Clear();};
 }
 void AddMediaRow(string title,TextBox box,int y,string filter){AddMediaRow(aiSettings,title,box,y,filter);}
 void AddMediaRow(Panel panel,string title,TextBox box,int y,string filter){panel.Controls.Add(new Label{Text=title,Left=16,Top=y,Width=128,Height=30,TextAlign=ContentAlignment.MiddleLeft});box.SetBounds(150,y,440,26);var b=new Button{Text="Chọn…",Left=600,Top=y,Width=80};panel.Controls.AddRange(new Control[]{box,b});b.Click+=delegate{using(var f=new OpenFileDialog{Filter=filter})if(f.ShowDialog(this)==DialogResult.OK)box.Text=f.FileName;};}
 void SetPhotoLooks(){
  string[][] looks={new[]{"01 · Đen / phòng sách gốc","02 · Đỏ / phòng sách gốc","03 · Cam / phòng sách gốc","04 · Trắng / phòng sách gốc"},
   new[]{"05 · Đen / phòng sách gốc","06 · Đỏ / phòng sách gốc","07 · Cam / phòng sách gốc","08 · Trắng / phòng sách gốc"},
   new[]{"09 · Đen / phòng sách gốc","10 · Đỏ / phòng sách gốc","11 · Cam / phòng sách gốc","12 · Trắng / phòng sách gốc"},
   new[]{"13 · Đỏ đô / phòng sách","14 · Xanh ngọc / cây xanh","15 · Navy / thành phố","16 · Kem / tối giản"},
   new[]{"17 · Đỏ đô / phòng sách","18 · Xanh ngọc / cây xanh","19 · Navy / thành phố","20 · Kem / tối giản"}};
  int group=character.SelectedIndex;if(group<0||group>=looks.Length)return;
  shirt.BeginUpdate();try{shirt.Items.Clear();shirt.Items.AddRange(looks[group]);shirt.SelectedIndex=0;}finally{shirt.EndUpdate();}
 }
 void SetMode(){bool ai=mode.SelectedIndex==1,video=mode.SelectedIndex==2,photo=mode.SelectedIndex==3;aiSettings.Visible=ai;videoSettings.Visible=video;photoSettings.Visible=photo;normalSettings.Visible=!ai&&!video&&!photo;playVoice.Text=video?"Phát video":"Phát lời";pauseVoice.Text=video?"Tạm dừng video":"Tạm dừng lời";if(StopAvatar!=null)StopAvatar();}
 internal bool PlaybackReady{get{return !IsDisposed&&narration!=null&&playVoice.Enabled;}}
 internal bool NarrationPlaying{get{return !IsDisposed&&narration!=null&&narration.Playing;}}
 internal void PlayNarration(){if(PlaybackReady)narration.Play();}
 internal void PauseNarration(){if(PlaybackReady)narration.Pause();}
 void VoiceButtons(bool enabled){playVoice.Enabled=pauseVoice.Enabled=rewindVoice.Enabled=enabled;}
 void DisposeNarration(){VoiceButtons(false);if(StopAvatar!=null)StopAvatar();if(narration!=null){narration.Dispose();narration=null;}}
 void StyleButtons(Control parent){foreach(Control c in parent.Controls){var b=c as Button;if(b!=null){b.Height=34;b.Width=Math.Max(b.Width,TextRenderer.MeasureText(b.Text,b.Font).Width+28);b.TextAlign=ContentAlignment.MiddleCenter;b.FlatStyle=FlatStyle.Flat;b.FlatAppearance.BorderColor=Color.FromArgb(77,115,125);b.FlatAppearance.MouseOverBackColor=Color.FromArgb(49,109,119);b.BackColor=Color.FromArgb(34,77,87);b.ForeColor=Color.White;b.Padding=Padding.Empty;}StyleButtons(c);}}
 void Row(string label,Control c,int y){settings.Controls.Add(new Label{Text=label,Left=16,Top=y,Height=30,TextAlign=ContentAlignment.MiddleLeft,Width=128});c.SetBounds(150,y,530,26);settings.Controls.Add(c);}
 void StartAll(){
  if(pendingCapture!=null||sessions.Count>0)return;
  try{
   if(!File.Exists(encoder.Text)||!String.Equals(Path.GetFileName(encoder.Text),"ffmpeg.exe",StringComparison.OrdinalIgnoreCase))throw new Exception("Chọn ffmpeg.exe từ bản Windows của FFmpeg (ffmpeg.org/download.html).");
   bool ai=mode.SelectedIndex==1,video=mode.SelectedIndex==2,photo=mode.SelectedIndex==3;
   if(photo&&(!File.Exists(photoMp3.Text)||!String.Equals(Path.GetExtension(photoMp3.Text),".mp3",StringComparison.OrdinalIgnoreCase)))throw new Exception("Chọn MP3 cho ảnh nói.");
   if(photo&&ShowPhoto==null)throw new Exception("Mở từ cửa sổ DTTL để hiện nhân vật.");
   if(video&&(!File.Exists(spokenVideo.Text)||!String.Equals(Path.GetExtension(spokenVideo.Text),".mp4",StringComparison.OrdinalIgnoreCase)))throw new Exception("Chọn file MP4 có lời.");
   if(video&&ShowSpokenVideo==null)throw new Exception("Mở Quay / Livestream từ cửa sổ DTTL để hiện video.");
   if(ai&&(!File.Exists(mp3.Text)||!String.Equals(Path.GetExtension(mp3.Text),".mp3",StringComparison.OrdinalIgnoreCase)))throw new Exception("Chọn file MP3 lời đọc.");
   if(ai&&(!File.Exists(avatar.Text)||!String.Equals(Path.GetExtension(avatar.Text),".mp4",StringComparison.OrdinalIgnoreCase)))throw new Exception("Chọn file MP4 làm hình AI.");
   if(ai&&ShowAvatar==null)throw new Exception("Mở phần Quay / Livestream từ cửa sổ DTTL để hiện video AI.");
   var monitors=Screen.AllScreens;if(screen.SelectedIndex<0||screen.SelectedIndex>=monitors.Length||!screen.Text.StartsWith(monitors[screen.SelectedIndex].DeviceName+" •"))throw new Exception("Danh sách màn hình thay đổi. Đóng rồi mở lại cửa sổ quay.");
   var targets=new System.Collections.Generic.List<string>();var labels=new System.Collections.Generic.List<string>();
   for(int i=0;i<3;i++)if(live[i].Checked){targets.Add(BroadcastSession.Destination(urls[i].Text.Trim(),keys[i].Text.Trim()));labels.Add(live[i].Text);}
   if(!record.Checked&&targets.Count==0)throw new Exception("Chọn lưu video hoặc nền tảng để phát.");
   if(targets.Count>0&&MessageBox.Show(this,"Gửi màn hình đã chọn tới "+String.Join(", ",labels.ToArray())+"?\nKhi test YouTube, đặt buổi phát Riêng tư trong YouTube Studio trước.","Bắt đầu phát",MessageBoxButtons.OKCancel)!=DialogResult.OK)return;
   hasLive=targets.Count>0;lastError="";details.Visible=false;savedFile=null;if(record.Checked){using(var f=new SaveFileDialog{Filter="Video MP4|*.mp4",FileName="DTTL-"+DateTime.Now.ToString("yyyyMMdd-HHmmss")+".mp4"}){if(f.ShowDialog(this)!=DialogResult.OK)return;if(File.Exists(f.FileName))throw new Exception("Chọn tên file mới để giữ video cũ.");savedFile=f.FileName;}}
   if(photo){narration=new NarrationAudio(photoMp3.Text,photoListen.Checked);ShowPhoto(character.SelectedIndex,shirt.SelectedIndex,narration);}
   if(ai){narration=new NarrationAudio(mp3.Text,listen.Checked);ShowAvatar(encoder.Text,avatar.Text);}
   if(video){try{narration=new NarrationAudio(spokenVideo.Text,listenVideo.Checked);}catch(Exception ex){throw new Exception("Không đọc được tiếng trong MP4. Hãy dùng MP4 có âm thanh AAC. "+ex.Message);}ShowSpokenVideo(encoder.Text,spokenVideo.Text,narration);}
   settings.Enabled=false;start.Enabled=false;stop.Enabled=true;VoiceButtons(false);
   if(savedFile!=null){targets.Insert(0,savedFile);labels.Insert(0,"Video");}
   BeginPreparation(delegate{
    try{
     for(int i=0;i<targets.Count;i++){var session=new BroadcastSession();sessions.Add(session);names.Add(labels[i]);session.Start(encoder.Text,monitors[screen.SelectedIndex].Bounds,quality.SelectedIndex==0?720:1080,targets[i],labels[i]!="Video",audio.SelectedIndex>0?audio.Text:"",false,!ai&&!video&&!photo&&systemSound.Checked,narration);}
     poll.Start();UpdateStatus();
    }catch(Exception ex){StartFailed(ex);}
   });
  }catch(Exception ex){StartFailed(ex);}
 }
 void StartFailed(Exception ex){StopAll();WindowState=FormWindowState.Normal;lastError="Không thể bắt đầu: "+ex.Message;details.Visible=true;status.Text=lastError;MessageBox.Show(this,lastError,Text);}
 internal void BeginPreparation(Action capture){
  preparationSeconds=prepareSeconds.SelectedIndex==0?0:prepareSeconds.SelectedIndex==2?5:3;
  pendingCapture=capture;prepareSeconds.Enabled=false;settings.Enabled=false;start.Enabled=false;stop.Enabled=true;
  if(preparationSeconds==0){AdvancePreparation();return;}
  status.Text="Chuẩn bị "+preparationSeconds+" giây — chưa quay/gửi hình. Bấm Dừng để hủy.";
  prepareClock.Restart();WindowState=FormWindowState.Minimized;prepareTimer.Start();
 }
 void AdvancePreparation(){
  if(pendingCapture==null)return;
  if(preparationSeconds>0&&prepareClock.Elapsed.TotalSeconds<preparationSeconds)return;
  var capture=pendingCapture;pendingCapture=null;prepareTimer.Stop();prepareClock.Stop();capture();
 }
 void UpdateStatus(){
  var lines=new System.Collections.Generic.List<string>();bool any=false,ready=sessions.Count>0;
  for(int i=0;i<sessions.Count;i++){var s=sessions[i];bool running=s.Running;any|=running;ready&=running&&s.HasFrames;lines.Add(names[i]+": "+(running?(s.HasFrames?"đang quay/phát":"đang khởi động…"):"đã dừng (mã "+s.ExitCode+")"));if(!running&&s.ExitCode!=0){lastError=names[i]+": "+s.Error;details.Visible=true;}}
  VoiceButtons(narration!=null&&ready);if(narration!=null&&narration.Error!=null){lastError=narration.Error;details.Visible=true;}
  string media=mode.SelectedIndex==2?"MP4":"MP3",play=mode.SelectedIndex==2?"Phát video":"Phát lời";
  status.Text=String.Join(" • ",lines.ToArray())+(details.Visible?"\nBấm Chi tiết lỗi để xem nguyên nhân.":hasLive?"\nKiểm tra hình xem trước trên nền tảng để xác nhận nhận luồng.":"\nBấm Dừng để hoàn tất file MP4.")+(narration==null?"":narration.Playing?"\n"+media+" đang phát trực tiếp; không thu micro/tiếng máy.":narration.Ended?"\n"+media+" đã hết. Bấm "+play+" để phát lại.":"\n"+media+" đang chờ. Bấm "+play+" khi sẵn sàng.");
  if(!any){poll.Stop();foreach(var s in sessions)s.Dispose();sessions.Clear();names.Clear();DisposeNarration();settings.Enabled=true;prepareSeconds.Enabled=true;start.Enabled=true;stop.Enabled=false;}
 }
 internal void StopAll(){bool cancelled=pendingCapture!=null;pendingCapture=null;prepareTimer.Stop();prepareClock.Reset();prepareSeconds.Enabled=true;if(cancelled)savedFile=null;poll.Stop();foreach(var s in sessions)s.Dispose();sessions.Clear();names.Clear();DisposeNarration();settings.Enabled=true;start.Enabled=true;stop.Enabled=false;status.Text="Đã dừng."+(savedFile==null?"":" Video: "+savedFile+" — mở file để kiểm tra.");}
}
