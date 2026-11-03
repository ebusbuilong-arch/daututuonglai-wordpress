# Phần mềm livestream màn hình — DTTL v1.3.0

Windows desktop app with four recording/streaming modes: webcam and microphone; silent video plus MP3; voiced MP4; animated studio portrait plus MP3.

Photo mode uses three presenters and four shirt colors. FaceMotion retains the approved amplitude-driven mouth and eased blink timing. PhotoAvatar precomputes registered mouth/eyelid patches on the full studio portraits. Only these regions change. There are six mouth levels and four eye levels, with interpolated transitions; this is not phoneme recognition or 24 independent full-face emotions.

Start preparation defaults to three seconds (optional zero or five). It runs after validation, save selection and source setup. The control window minimizes while waiting. No BroadcastSession is created before the timer expires. Stop/close cancels pending capture. Existing media playback controls remain manual.

Run `./apps/screen-layout/build.ps1` on Windows. Tests use synthetic textures, verify all twelve character/color combinations, region isolation, mouth dynamics, blink timing, delayed start/cancel, docking, audio, local recording and streaming. Hardware cameras and public platform accounts require manual testing.

CI produces `Phan-mem-livestream-man-hinh-v1.3.0.zip` with EXE and pinned dependencies. Private delivery adds `avatars.jpg` and `face-transitions.png`; never upload presenter artwork to the source repository. FFmpeg is supplied separately. See `HUONG-DAN-MAN-HINH-AO.txt` for installation and use.
