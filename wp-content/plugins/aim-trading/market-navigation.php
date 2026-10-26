<?php defined('ABSPATH') || exit; ?>
<nav class="market-nav" aria-label="Thị trường giao dịch">
<?php foreach(array('crypto'=>'Crypto','stock'=>'Cổ phiếu','forex'=>'Forex','commodity'=>'Hàng hóa') as $key=>$label): $selected=($aim_market_tab===$key); ?>
<a class="<?php echo $selected?'selected':''; ?>" <?php echo $selected?'aria-current="page"':''; ?> href="<?php echo esc_url(home_url('/aim-trading/').($key==='crypto'?'':'?mode='.$key)); ?>"><?php echo esc_html($label); ?><small><?php echo $key==='crypto'?'24/7':'Theo dõi'; ?></small></a>
<?php endforeach; ?>
<?php if(!empty($aim_show_connection)): ?><span id="connection">Đang kết nối nguồn giá…</span><?php endif; ?>
</nav>
