<?php
/** @var array $view */
if (!defined('ABSPATH')) {
  exit;
}

$data = isset($view['data']) ? $view['data'] : array();
$menus = isset($data['menus']) && is_array($data['menus']) ? $data['menus'] : array();

if (empty($menus) && isset($data['menuName'])) {
  $menus = array($data);
}

// Prepare the highlighted items for efficient lookup.
$highlights_lookup = array();
if (!empty($view['data']['highlighted_items']) && is_array($view['data']['highlighted_items'])) {
  foreach ($view['data']['highlighted_items'] as $h) {
    if (!empty($h['item_identifier']) && !empty($h['highlight_type'])) {
      // The key is already the unique 'Category::Item' string from the select field.
      $key = $h['item_identifier'];
      // The value is the CSS class suffix.
      $highlights_lookup[$key] = sanitize_html_class($h['highlight_type']);
    }
  }
}

$expanded = !empty($view['expanded']);
$show_prices = !empty($view['show_prices']);
$layout = isset($view['layout']) && in_array($view['layout'], array('accordion', 'tabs'), true) ? $view['layout'] : 'accordion';
$cat_display = isset($view['category_display']) && in_array($view['category_display'], array('accordion', 'expanded'), true) ? $view['category_display'] : 'accordion';
$nutrition_visibility = isset($view['nutrition_visibility']) && in_array($view['nutrition_visibility'], array('hide', 'show'), true) ? $view['nutrition_visibility'] : 'hide';
$labels_visibility = isset($view['labels_visibility']) && in_array($view['labels_visibility'], array('show', 'hide'), true) ? $view['labels_visibility'] : 'show';
$item_columns = isset($view['item_columns']) && in_array((string) $view['item_columns'], array('1', '2'), true) ? (string) $view['item_columns'] : '1';

$instance = 'sp-menu-' . (function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid());
$wrapper_class = 'sp-menu sp-menu--layout-' . $layout . ' sp-menu--cat-' . $cat_display . ' sp-menu--cols-' . $item_columns;
if (isset($view['choices'])) {
  $wrapper_class .= ' sp-menu--grouped';
}
$attrs = function_exists('get_block_wrapper_attributes')
  ? get_block_wrapper_attributes(array('class' => $wrapper_class))
  : 'class="' . esc_attr($wrapper_class) . '"';

$heading = static function ($menu) {
  return ($menu['menuName'] ?? '') !== ''
    ? $menu['menuName']
    : (($menu['locationName'] ?? '') ?: __('Menu', 'sp-menu'));
};
$render_tabs = static function ($labels, $prefix, $label, $class = '') {
  ?>
  <nav class="sp-menu__tabs<?php echo esc_attr($class); ?>" role="tablist" aria-label="<?php echo esc_attr($label); ?>">
    <?php foreach ($labels as $index => $text): ?>
      <button type="button" class="sp-menu__tab" role="tab" id="<?php echo esc_attr($prefix . '-tab-' . $index); ?>"
        aria-controls="<?php echo esc_attr($prefix . '-panel-' . $index); ?>" aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>"
        tabindex="<?php echo $index === 0 ? '0' : '-1'; ?>"><?php echo esc_html($text); ?></button>
    <?php endforeach; ?>
  </nav>
  <?php
};
$panel_attributes = static function ($prefix, $index, $tabs) {
  $attrs = 'class="sp-menu__group" id="' . esc_attr($prefix . '-panel-' . $index) . '"';
  if ($tabs) {
    $attrs .= ' role="tabpanel" tabindex="0" aria-labelledby="' . esc_attr($prefix . '-tab-' . $index) . '"';
    if ($index !== 0) {
      $attrs .= ' hidden';
    }
  }
  return $attrs;
};

$render_menu = static function ($menu, $menu_heading, $panel_id) use ($view, $cat_display, $expanded, $show_prices, $highlights_lookup, $labels_visibility, $nutrition_visibility) {
  $currency = $menu['currency'] ?? ($view['currency'] ?? 'USD');
  ?>
        <h2 class="sp-menu__title"><?php echo esc_html($menu_heading); ?></h2>

        <?php if (!empty($menu['categories'])): ?>
          <?php foreach ($menu['categories'] as $i => $cat):
            $cid = $panel_id . '-cat-' . (int) $i;
            ?>
            <div class="sp-menu__category">
              <?php if ($cat_display === 'accordion'): ?>
                <button type="button" class="sp-menu__toggle" aria-controls="<?php echo esc_attr($cid); ?>"
                  aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>">
                  <?php echo esc_html($cat['name']); ?>
                </button>
                <ul id="<?php echo esc_attr($cid); ?>" class="sp-menu__items" <?php echo $expanded ? '' : ' hidden'; ?>>
                <?php else: ?>
                  <div class="sp-menu__category-name"><?php echo esc_html($cat['name']); ?></div>
                  <ul id="<?php echo esc_attr($cid); ?>" class="sp-menu__items">
                  <?php endif; ?>
                  <?php if (!empty($cat['items'])): ?>
                    <?php foreach ($cat['items'] as $item):

                      // --- Highlighting logic ---
                      $item_classes = ['sp-menu__item'];
                      $current_key = $cat['name'] . '::' . $item['name'];
                      if (isset($highlights_lookup[$current_key])) {
                        $highlight_class = 'sp-menu__item--' . $highlights_lookup[$current_key];
                        $item_classes[] = $highlight_class;
                      }
                      // --- End highlighting logic ---
          
                      ?>
                      <li class="<?php echo esc_attr(implode(' ', $item_classes)); ?>">
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

                        <?php if ($labels_visibility === 'show' && !empty($item['tags'])): ?>
                          <small class="sp-menu__tags"><?php echo esc_html(implode(' • ', $item['tags'])); ?></small>
                        <?php endif; ?>

                        <?php if (!empty($item['desc'])): ?>
                          <div class="sp-menu__item-desc"><?php echo wp_kses_post($item['desc']); ?></div>
                        <?php endif; ?>

                        <?php if ($nutrition_visibility === 'show' && !empty($item['nutrition']) && is_array($item['nutrition'])): ?>
                          <div class="sp-menu__nutrition">
                            <dl class="sp-menu__nutrition-list">
                              <?php foreach ($item['nutrition'] as $n):
                                $n_label = isset($n['label']) ? $n['label'] : '';
                                $n_value = isset($n['value']) ? $n['value'] : '';
                                if ($n_label === '' && $n_value === '')
                                  continue;
                                ?>
                                <div class="sp-menu__nutrition-row">
                                  <dt class="sp-menu__nutrition-label"><?php echo esc_html($n_label); ?></dt>
                                  <dd class="sp-menu__nutrition-value"><?php echo esc_html($n_value); ?></dd>
                                </div>
                              <?php endforeach; ?>
                            </dl>
                          </div>
                        <?php endif; ?>

                        <?php if (!empty($item['additions'])): ?>
                          <ul class="sp-menu__additions">
                            <?php foreach ($item['additions'] as $add): ?>
                              <li>
                                <span class="sp-menu__addition-name"><?php echo esc_html($add['name']); ?></span>
                                <?php if ($show_prices && isset($add['price'])): ?>
                                  <span
                                    class="sp-menu__addition-price"><?php echo esc_html(\PRG\SinglePlatform\format_price($add['price'], $currency)); ?></span>
                                <?php endif; ?>
                              </li>
                            <?php endforeach; ?>
                          </ul>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <li class="sp-menu__item sp-menu__item--empty"><?php echo esc_html__('No items available', 'sp-menu'); ?></li>
                  <?php endif; ?>
                </ul>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="sp-menu__empty"><?php echo esc_html__('No categories available', 'sp-menu'); ?></p>
        <?php endif; ?>

        <?php if (!empty($menu['footnote'])): ?>
          <p class="sp-menu__footnote"><small><?php echo wp_kses_post($menu['footnote']); ?></small></p>
        <?php endif; ?>
  <?php
};

$choices = $view['choices'] ?? array_map(static function ($menu) {
  return array('label' => '', 'menus' => array($menu));
}, $menus);
$labels = array_map(static function ($choice) use ($heading) {
  return $choice['label'] !== '' ? $choice['label'] : $heading($choice['menus'][0]);
}, $choices);
$tabs = $layout === 'tabs';
?>
<section <?php echo $attrs; ?>>
  <?php if ($tabs && $choices): ?>
    <?php $render_tabs($labels, $instance, __('Menu selection', 'sp-menu')); ?>
  <?php endif; ?>

  <?php foreach ($choices as $index => $choice): ?>
    <?php $panel_id = $instance . '-panel-' . $index; ?>
    <div <?php echo $panel_attributes($instance, $index, $tabs); ?>>
      <?php if (count($choice['menus']) > 1): ?>
        <?php
        $member_labels = array_map($heading, $choice['menus']);
        $render_tabs($member_labels, $panel_id, $labels[$index], ' sp-menu__tabs--members');
        ?>
        <?php foreach ($choice['menus'] as $member_index => $menu): ?>
          <div <?php echo $panel_attributes($panel_id, $member_index, true); ?>>
            <?php $render_menu($menu, $member_labels[$member_index], $panel_id . '-panel-' . $member_index); ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <?php $render_menu($choice['menus'][0], $labels[$index], $panel_id); ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$choices): ?>
    <p class="sp-menu__empty"><?php echo esc_html__('No menus available', 'sp-menu'); ?></p>
  <?php endif; ?>
</section>
