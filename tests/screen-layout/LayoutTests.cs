using System;
using System.Drawing;
class LayoutTests {
 [STAThread] public static void Main(){int count=0;foreach(var area in new[]{new Rectangle(0,0,1366,728),new Rectangle(-1920,0,1920,1040),new Rectangle(0,-2160,3840,2120),new Rectangle(1920,0,1080,1880)})for(int k=0;k<5;k++)for(int ratio=30;ratio<=70;ratio+=10)foreach(int gap in new[]{0,8}){
 var cells=Layouts.Make(area,k,ratio,gap);long total=0;for(int i=0;i<cells.Length;i++){if(!area.Contains(cells[i])||cells[i].Width<=0||cells[i].Height<=0)throw new Exception("Out of bounds");total+=(long)cells[i].Width*cells[i].Height;for(int j=0;j<i;j++)if(cells[i].IntersectsWith(cells[j]))throw new Exception("Overlap");}if(gap==0&&total!=(long)area.Width*area.Height)throw new Exception("Missing area");count++;}
 System.Windows.Forms.Application.EnableVisualStyles();
 using(var form=new MainForm()) {
  form.Show();System.Windows.Forms.Application.DoEvents();
  using(var bitmap=new Bitmap(form.Width,form.Height)) { form.DrawToBitmap(bitmap,new Rectangle(0,0,form.Width,form.Height));bitmap.Save("apps/screen-layout/dist/app-preview.png",System.Drawing.Imaging.ImageFormat.Png); }
  form.Close();
 }
 Console.WriteLine("PASS: "+count+" layout cases; negative monitor origins, portrait, ultrawide and gaps.");
 }
}
