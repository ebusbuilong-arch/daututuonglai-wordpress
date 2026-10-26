<?php defined('ABSPATH') || exit; ?>
<nav class="market-nav practice-nav" aria-label="Thị trường luyện tập"><span class="practice-label">LUYỆN TẬP</span>
<?php foreach(array('crypto'=>'Crypto & quản lý vốn','stock'=>'Cổ phiếu','forex'=>'Forex','commodity'=>'Hàng hóa') as $key=>$label): $selected=($aim_market_tab===$key); ?>
<a class="<?php echo $selected?'selected':''; ?>" <?php echo $selected?'aria-current="page"':''; ?> href="<?php echo esc_url(home_url('/aim-trading/?mode=workshop').($key==='crypto'?'':'&market='.$key)); ?>"><?php echo esc_html($label); ?></a>
<?php endforeach; ?></nav>
