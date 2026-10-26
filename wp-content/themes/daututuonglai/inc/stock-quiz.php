<?php
if (!defined('ABSPATH')) exit;
function dttl_stock_quiz() {
    $questions=json_decode(file_get_contents(get_template_directory().'/stock-quiz.json'),true);
    if(!is_array($questions)) return '<p>Bài kiểm tra đang được cập nhật.</p>';
    $uid=wp_unique_id('dttl-quiz-');
    ob_start(); ?>
    <form class="stock-quiz" data-stock-quiz novalidate>
        <div class="quiz-intro"><span class="eyebrow dark">TỰ KIỂM TRA</span><h2>12 câu để hiểu chắc nền tảng</h2><p>Mỗi câu chọn một đáp án. Xem điểm và lời giải sau khi hoàn thành.</p></div>
        <p class="quiz-progress" aria-live="polite">Đã trả lời 0/<?php echo count($questions); ?> câu</p>
        <?php foreach($questions as $i=>$q): ?>
        <fieldset class="quiz-question" data-answer="<?php echo (int)$q['answer']; ?>">
            <legend><?php echo esc_html(($i+1).'. '.$q['question']); ?></legend>
            <?php foreach($q['options'] as $j=>$option): ?>
            <label><input type="radio" name="<?php echo esc_attr($uid.$i); ?>" value="<?php echo (int)$j; ?>"><span><?php echo esc_html($option); ?></span></label>
            <?php endforeach; ?>
            <div class="quiz-explanation" hidden><strong class="quiz-verdict"></strong><p><?php echo esc_html($q['explanation']); ?></p></div>
        </fieldset>
        <?php endforeach; ?>
        <div class="quiz-actions"><button type="submit" class="button primary">Chấm điểm</button><button type="reset" class="button secondary">Làm lại</button></div>
        <div class="quiz-result" role="status" aria-live="polite" tabindex="-1"></div>
        <noscript><p>Bật JavaScript để chấm điểm. Bạn vẫn có thể đọc câu hỏi và ôn các bài nền tảng.</p></noscript>
    </form>
    <?php return ob_get_clean();
}
add_shortcode('dttl_stock_quiz','dttl_stock_quiz');
