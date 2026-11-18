# Phần mềm livestream màn hình — DTTL v1.3.1

Windows desktop app with four recording/streaming modes: webcam and microphone; silent video plus MP3; voiced MP4; animated studio portrait plus MP3.

Photo mode uses three presenters and four shirt colors. FaceMotion retains the approved amplitude-driven mouth and eased blink timing. PhotoAvatar opens the original portrait lips around their existing smile line, keeps the corners anchored, and inserts only inner-mouth texture. It does not replace the lips or surrounding skin with a donor face. Four subpixel samples smooth tilted lip edges. Eyelid registration is unchanged. Only these regions change. There are six mouth levels and four eye levels, with interpolated transitions; this is not phoneme recognition or 24 independent full-face emotions.

Start preparation defaults to three seconds (optional zero or five). It runs after validation, save selection and source setup. The control window minimizes while waiting. No BroadcastSession is created before the timer expires. Stop/close cancels pending capture. Existing media playback controls remain manual.

Run `./apps/screen-layout/build.ps1` on Windows. Tests use synthetic textures, verify all twelve character/color combinations, region isolation, mouth dynamics, blink timing, delayed start/cancel, docking, audio, local recording and streaming. Hardware cameras and public platform accounts require manual testing.

CI produces `Phan-mem-livestream-man-hinh-v1.3.1.zip` with EXE and pinned dependencies. Private delivery adds `avatars.jpg` and `face-transitions.png`; never upload presenter artwork to the source repository. FFmpeg is supplied separately. See `HUONG-DAN-MAN-HINH-AO.txt` for installation and use.


### v1.3.2 approved-face sample
Photo mode temporarily exposes one long-haired, black-shirt studio sample. Register the master neutral face once and transfer the original mouth/eyelid sprite differences with a single transform. This supersedes the v1.3.1 own-lip warp. Private studio-approved.png and face-transitions.png are added only to the delivery package. Other capture modes and preparation delay are unchanged.


### v1.4.0 complete wardrobe
Restore 3 hair styles x 4 shirt colors. Long hair/black retains the exact v1.3.2 asset and transform. Other looks use private studio-wardrobe.png with a shared registered master face, mouth sprites, and blink. Runtime photo rendering requires no intermediate MP4.
