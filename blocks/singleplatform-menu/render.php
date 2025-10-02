<?php
/** @var array $view */
if (!defined('ABSPATH')) { exit; }
$data        = isset($view['data']) ? $view['data'] : array();
$menus       = isset($data['menus']) && is_array($data['menus']) ? $data['menus'] : array();

if (empty($menus) && isset($data['menuName'])) {
  $menus = array($data);
}
$expanded    = !empty($view['expanded']);
$show_prices = !empty($view['show_prices']);

$attrs = function_exists('get_block_wrapper_attributes') ? get_block_wrapper_attributes(array('class' => 'sp-menu')) : 'class="sp-menu"';
?>
<section <?php echo $attrs; ?>>
  <?php if (!empty($menus)): foreach ($menus as $m_index => $menu):
    $menu_heading = isset($menu['menuName']) && $menu['menuName'] !== ''
      ? $menu['menuName']
      : (isset($menu['locationName']) ? $menu['locationName'] : __('Menu', 'sp-menu'));
    $currency = isset($menu['currency']) ? $menu['currency'] : (isset($view['currency']) ? $view['currency'] : 'USD');
  ?>
    <article class="sp-menu__group">
      <h2 class="sp-menu__title"><?php echo esc_html($menu_heading); ?></h2>

      <?php if (!empty($menu['categories'])): foreach ($menu['categories'] as $i => $cat):
        $cid = 'sp-cat-' . (int) $m_index . '-' . (int) $i; ?>
        <div class="sp-menu__category">
          <button type="button" class="sp-menu__toggle" aria-controls="<?php echo esc_attr($cid); ?>" aria-expanded="<?php echo $expanded ? 'true':'false'; ?>">
            <?php echo esc_html($cat['name']); ?>
          </button>
          <ul id="<?php echo esc_attr($cid); ?>" class="sp-menu__items"<?php if (!$expanded) echo ' hidden'; ?>>
            <?php if (!empty($cat['items'])): foreach ($cat['items'] as $item): ?>
              <li class="sp-menu__item">
                <div class="sp-menu__row">
                  <span class="sp-menu__item-name"><?php echo esc_html($item['name']); ?></span>
                  <?php if ($show_prices): ?>
                    <span class="sp-menu__price">
                      <?php
                      if (!empty($item['market'])) {
                          echo esc_html__('MP', 'sp-menu');
                      } elseif (isset($item['price'])) {
                          echo esc_html(\PRG\SinglePlatform\format_price($item['price'], $currency));
                      }
                      ?>
                    </span>
                  <?php endif; ?>
                </div>

                <?php if (!empty($item['tags'])): ?>
                  <small class="sp-menu__tags"><?php echo esc_html(implode(' • ', $item['tags'])); ?></small>
                <?php endif; ?>

                <?php if (!empty($item['desc'])): ?>
                  <div class="sp-menu__item-desc"><?php echo wp_kses_post($item['desc']); ?></div>
                <?php endif; ?>

                <?php if (!empty($item['additions'])): ?>
                  <ul class="sp-menu__additions">
                    <?php foreach ($item['additions'] as $add): ?>
                      <li>
                        <span class="sp-menu__addition-name"><?php echo esc_html($add['name']); ?></span>
                        <?php if ($show_prices && isset($add['price'])): ?>
                          <span class="sp-menu__addition-price"><?php echo esc_html(\PRG\SinglePlatform\format_price($add['price'], $currency)); ?></span>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </li>
            <?php endforeach; else: ?>
              <li class="sp-menu__item sp-menu__item--empty"><?php echo esc_html__('No items available', 'sp-menu'); ?></li>
            <?php endif; ?>
          </ul>
        </div>
      <?php endforeach; else: ?>
        <p class="sp-menu__empty"><?php echo esc_html__('No categories available', 'sp-menu'); ?></p>
      <?php endif; ?>

      <?php if (!empty($menu['footnote'])): ?>
        <p class="sp-menu__footnote"><small><?php echo wp_kses_post($menu['footnote']); ?></small></p>
      <?php endif; ?>

      <?php if (!empty($menu['attribution']['img'])): ?>
        <p class="sp-menu__attrib">
          <a href="<?php echo esc_url($menu['attribution']['href']); ?>" rel="nofollow noopener" target="_blank">
            <img src="<?php echo esc_url($menu['attribution']['img']); ?>" alt="<?php echo esc_attr__('Provided by SinglePlatform', 'sp-menu'); ?>" loading="lazy" />
          </a>
        </p>
      <?php endif; ?>
    </article>
  <?php endforeach; else: ?>
    <p class="sp-menu__empty"><?php echo esc_html__('No menus available', 'sp-menu'); ?></p>
  <?php endif; ?>
</section>
<script>
(function(){
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root) return;
  root.addEventListener('click', function(e){
    var btn = e.target.closest('.sp-menu__toggle');
    if (!btn) return;
    var id = btn.getAttribute('aria-controls');
    var panel = root.querySelector('#' + id);
    if (!panel) return;
    var expanded = btn.getAttribute('aria-expanded') === 'true';
    btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    if (expanded) { panel.setAttribute('hidden', ''); } else { panel.removeAttribute('hidden'); }
  });
})();
</script>
