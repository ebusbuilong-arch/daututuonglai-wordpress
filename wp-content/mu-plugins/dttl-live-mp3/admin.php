<?php
defined('ABSPATH') || exit;

$settings = self::settings();
$has_key = self::key() !== '';
$flash_key = self::PAGE . '-' . get_current_user_id();
$flash = get_transient($flash_key);
delete_transient($flash_key);
$page = max(1, min(4000, absint($_GET['mp3page'] ?? 1)));
$rows = self::jobs($page);
$post_url = admin_url('admin-post.php');
$download_base = add_query_arg('action', 'dttl_live_mp3_download', $post_url);
?>
<div class="wrap dttl-live-mp3" id="dttl-live-mp3" data-page="<?php echo esc_attr($page); ?>" data-download="<?php echo esc_url($download_base); ?>">
    <h1>MP3 bài live</h1>
    <?php if ($flash) : ?>
        <div class="notice <?php echo $flash[1] ? 'notice-error' : 'notice-success'; ?> is-dismissible"><p><?php echo esc_html($flash[0]); ?></p></div>
    <?php endif; ?>
    <div class="dttl-mp3-grid">
        <section class="dttl-mp3-card">
            <h2>Nhập kịch bản</h2>
            <form method="post" action="<?php echo esc_url($post_url); ?>" id="dttl-mp3-import">
                <input type="hidden" name="action" value="dttl_live_mp3_import">
                <?php wp_nonce_field('dttl_live_mp3_import'); ?>
                <label for="dttl-mp3-files">Chọn một gói JSON hoặc tối đa 10 file TXT</label>
                <input type="file" id="dttl-mp3-files" multiple accept=".json,.txt,application/json,text/plain">
                <p class="description">Tên file TXT cần có số bài, ví dụ 063.txt hoặc LIVE-063-Loi-nhuan-thuc.txt.</p>
                <label for="dttl-mp3-batch">Nội dung gói JSON</label>
                <textarea id="dttl-mp3-batch" name="batch" rows="6" spellcheck="false" placeholder='[{"lesson":63,"title":"Lợi nhuận thực","text":"Kịch bản đọc"}]' required></textarea>
                <p id="dttl-mp3-preview" aria-live="polite">Tối đa 25.000 ký tự mỗi bài.</p>
                <button class="button button-primary" type="submit"><?php echo $has_key && $settings['voice'] ? 'Nhập và tạo MP3' : 'Nhập kịch bản'; ?></button>
                <p class="description">EverAI tính credit khi tạo audio. Các số bài đã có trong hàng đợi sẽ được giữ lại.</p>
            </form>
        </section>
        <section class="dttl-mp3-card">
            <h2>Cấu hình EverAI</h2>
            <form method="post" action="<?php echo esc_url($post_url); ?>" autocomplete="off">
                <input type="hidden" name="action" value="dttl_live_mp3_settings">
                <?php wp_nonce_field('dttl_live_mp3_settings'); ?>
                <label for="dttl-mp3-key">Khóa API</label>
                <input type="password" id="dttl-mp3-key" name="api_key" maxlength="512" autocomplete="new-password"
                       placeholder="<?php echo $has_key ? 'Đã lưu khóa API' : 'Dán khóa từ EverAI'; ?>">
                <label for="dttl-mp3-voice">Mã giọng đọc</label>
                <input type="text" id="dttl-mp3-voice" name="voice" maxlength="120" required
                       value="<?php echo esc_attr($settings['voice']); ?>">
                <p class="description">Lấy mã bằng nút ID cạnh giọng đọc trong EverAI.</p>
                <div class="dttl-mp3-voice-settings">
                    <label>Mô hình
                        <select name="model">
                            <?php foreach (['everai-v1.6' => 'EverAI v1.6', 'everai-v1.5' => 'EverAI v1.5', 'everai-v1.5-turbo' => 'EverAI v1.5 Turbo'] as $model => $label) : ?>
                                <option value="<?php echo esc_attr($model); ?>" <?php selected($settings['model'], $model); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Tốc độ
                        <input type="number" name="speed" min="0.5" max="2" step="0.1" value="<?php echo esc_attr($settings['speed']); ?>" required>
                    </label>
                </div>
                <button class="button" type="submit">Lưu cấu hình</button>
            </form>
        </section>
    </div>
    <section class="dttl-mp3-card dttl-mp3-history">
        <h2>Hàng đợi và MP3</h2>
        <p id="dttl-mp3-status" role="status"><?php echo $has_key ? 'Giữ trang này mở để theo dõi tiến độ và tải MP3 khi hoàn thành.' : 'Cần cấu hình EverAI để tạo MP3.'; ?></p>
        <div class="dttl-mp3-scroll">
            <table class="widefat striped">
                <thead><tr><th scope="col">Bài</th><th scope="col">Tiêu đề</th><th scope="col">Trạng thái</th><th scope="col">Tệp</th></tr></thead>
                <tbody id="dttl-mp3-rows">
                    <tr><td colspan="4">Đang tải hàng đợi…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="dttl-mp3-pages">
            <?php if ($page > 1) : ?>
                <a class="button" href="<?php echo esc_url(add_query_arg('mp3page', $page - 1)); ?>">Trang trước</a>
            <?php endif; ?>
            <?php if (count($rows) === 30) : ?>
                <a class="button" href="<?php echo esc_url(add_query_arg('mp3page', $page + 1)); ?>">Trang sau</a>
            <?php endif; ?>
        </div>
    </section>
</div>
