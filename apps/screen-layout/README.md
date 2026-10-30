# DTTL Screen Layout 0.1 beta

Windows Forms / .NET Framework, AnyCPU portable utility. UI Vietnamese. No network calls, startup registration, admin requirement or telemetry.

## Build
Run `powershell -ExecutionPolicy Bypass -File apps/screen-layout/build.ps1` from repository root on a Windows development machine with .NET Framework compiler installed. ExecutionPolicy applies to this build command only. The app itself does not need PowerShell.

Workflow `Build DTTL Screen Layout` compiles, runs 200 geometry cases, and produces two ZIPs: member download and installable WordPress plugin. Distribution file is embedded as PHP data with direct execution denied, avoiding a publicly accessible uploads ZIP. Do not commit generated payload or binaries to this public repository.

## WordPress installation
Install the generated `dttl-free-apps.zip` through Plugins, activate, then Tools → Ứng dụng miễn phí → Tạo và xuất bản trang ứng dụng. Uses existing WordPress registration/login. Does not change registration settings, menu, theme or existing articles. New page contains the SEO article and `[dttl_screen_layout_download]` shortcode. Test the download both signed out and as a normal member before announcing it. Purge page cache after replacing payload. Server must execute `.php` rather than serve source.

Rollback: deactivate plugin; unpublish the created page if needed. No existing data is deleted. No changes to the current production deployment allowlist.

## Release limitations
Build and automated tests are not equivalent to manual compatibility certification. Before stable release, test Windows 10 and 11, 100/125/150/200% DPI, mixed-DPI dual monitors, taskbar edges, disconnected monitor, minimum-size apps, minimized/maximized windows, undo, duplicate selections, stale handles, elevated windows and hotkey collision. Unsigned beta; do not recommend disabling security protections. No Mac/Linux/ARM release yet.
