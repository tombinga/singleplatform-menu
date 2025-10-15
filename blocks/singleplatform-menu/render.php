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

$expanded = !empty($view['expanded']);
$show_prices = !empty($view['show_prices']);
$layout = isset($view['layout']) && in_array($view['layout'], array('accordion', 'tabs'), true) ? $view['layout'] : 'accordion';
$cat_display = isset($view['category_display']) && in_array($view['category_display'], array('accordion', 'expanded'), true) ? $view['category_display'] : 'accordion';
$nutrition_visibility = isset($view['nutrition_visibility']) && in_array($view['nutrition_visibility'], array('hide', 'show'), true) ? $view['nutrition_visibility'] : 'hide';
$labels_visibility = isset($view['labels_visibility']) && in_array($view['labels_visibility'], array('show', 'hide'), true) ? $view['labels_visibility'] : 'show';
$item_columns = isset($view['item_columns']) && in_array((string) $view['item_columns'], array('1', '2'), true) ? (string) $view['item_columns'] : '1';

$instance = function_exists('wp_unique_id') ? wp_unique_id('sp-menu-') : uniqid('sp-menu-');
$wrapper_class = 'sp-menu sp-menu--layout-' . $layout . ' sp-menu--cat-' . $cat_display . ' sp-menu--cols-' . $item_columns;
$attrs = function_exists('get_block_wrapper_attributes')
  ? get_block_wrapper_attributes(array('class' => $wrapper_class))
  : 'class="' . esc_attr($wrapper_class) . '"';

$menu_count = count($menus);
?>
<section <?php echo $attrs; ?>>
  <?php if ($layout === 'tabs' && $menu_count > 0): ?>
    <nav class="sp-menu__tabs" role="tablist" aria-label="<?php echo esc_attr__('Menu selection', 'sp-menu'); ?>">
      <?php foreach ($menus as $m_index => $menu):
        $menu_heading = isset($menu['menuName']) && $menu['menuName'] !== ''
          ? $menu['menuName']
          : (isset($menu['locationName']) ? $menu['locationName'] : __('Menu', 'sp-menu'));
        $tab_id = $instance . '-tab-' . (int) $m_index;
        $panel_id = $instance . '-panel-' . (int) $m_index;
        $is_active = ($m_index === 0);
        ?>
        <button type="button" class="sp-menu__tab" role="tab" id="<?php echo esc_attr($tab_id); ?>"
          aria-controls="<?php echo esc_attr($panel_id); ?>" aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
          tabindex="<?php echo $is_active ? '0' : '-1'; ?>" data-sp-menu-target="<?php echo esc_attr($panel_id); ?>">
          <?php echo esc_html($menu_heading); ?>
        </button>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <?php if ($menu_count > 0): ?>
    <?php foreach ($menus as $m_index => $menu):
      $menu_heading = isset($menu['menuName']) && $menu['menuName'] !== ''
        ? $menu['menuName']
        : (isset($menu['locationName']) ? $menu['locationName'] : __('Menu', 'sp-menu'));
      $currency = isset($menu['currency']) ? $menu['currency'] : (isset($view['currency']) ? $view['currency'] : 'USD');
      $panel_id = $instance . '-panel-' . (int) $m_index;
      $tab_id = $instance . '-tab-' . (int) $m_index;
      $is_active_panel = ($layout !== 'tabs') || $m_index === 0;

      $article_attrs = array('class="sp-menu__group"', 'id="' . esc_attr($panel_id) . '"');
      if ($layout === 'tabs') {
        $article_attrs[] = 'role="tabpanel"';
        $article_attrs[] = 'aria-labelledby="' . esc_attr($tab_id) . '"';
        if (!$is_active_panel) {
          $article_attrs[] = 'hidden';
        }
      }
      ?>
      <div <?php echo implode(' ', $article_attrs); ?>>
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
                    <?php foreach ($cat['items'] as $item): ?>
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

      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p class="sp-menu__empty"><?php echo esc_html__('No menus available', 'sp-menu'); ?></p>
  <?php endif; ?>
</section>
<script>
  (function () {
    var root = document.currentScript && document.currentScript.previousElementSibling;
    if (!root) return;

    if (root.classList.contains('sp-menu--layout-tabs')) {
      var tabs = Array.prototype.slice.call(root.querySelectorAll('[role="tab"]'));
      var panels = Array.prototype.slice.call(root.querySelectorAll('[role="tabpanel"]'));
      if (tabs.length && panels.length) {
        var activate = function (tab) {
          var targetId = tab.getAttribute('data-sp-menu-target');
          tabs.forEach(function (t) {
            var selected = t === tab;
            t.setAttribute('aria-selected', selected ? 'true' : 'false');
            t.setAttribute('tabindex', selected ? '0' : '-1');
          });
          panels.forEach(function (panel) {
            if (panel.id === targetId) {
              panel.removeAttribute('hidden');
            } else {
              panel.setAttribute('hidden', '');
            }
          });
        };

        root.addEventListener('click', function (e) {
          var tab = e.target.closest('[role="tab"]');
          if (!tab || !root.contains(tab)) return;
          e.preventDefault();
          activate(tab);
          tab.focus();
        });

        root.addEventListener('keydown', function (e) {
          var tab = e.target.closest('[role="tab"]');
          if (!tab || !root.contains(tab)) return;
          var index = tabs.indexOf(tab);
          if (index === -1) return;
          var nextIndex = null;
          if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
            nextIndex = (index + 1) % tabs.length;
          } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
            nextIndex = (index - 1 + tabs.length) % tabs.length;
          } else if (e.key === 'Home') {
            nextIndex = 0;
          } else if (e.key === 'End') {
            nextIndex = tabs.length - 1;
          }
          if (nextIndex !== null) {
            e.preventDefault();
            var nextTab = tabs[nextIndex];
            activate(nextTab);
            nextTab.focus();
          }
        });
      }
    }

    if (root.classList.contains('sp-menu--cat-accordion')) {
      root.addEventListener('click', function (e) {
        var btn = e.target.closest('.sp-menu__toggle');
        if (!btn) return;
        var id = btn.getAttribute('aria-controls');
        var panel = root.querySelector('#' + id);
        var expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        if (expanded) { panel.setAttribute('hidden', ''); } else { panel.removeAttribute('hidden'); }
      });
    }
  })();
</script>