<?php
/**
 * Shared admin UI components for the 2.0.2 design system.
 *
 * Every method echoes escaped markup using the `gb2-` namespace defined in
 * assets/css/admin-2.css. Nothing here reads options or queries the database —
 * callers pass plain arrays, which keeps the renderers testable and stops page
 * templates from growing their own private markup again.
 *
 * @package GeekyBot
 * @since 2.0.2
 */

namespace GeekyBot\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Components {

    /**
     * Compact page header. Replaces the full-bleed hero used in 2.0.1.
     *
     * @param array $args {
     *     @type string   $title       Page title. Required.
     *     @type string   $description Single supporting sentence. Optional.
     *     @type callable $brand       Callable that echoes the brand mark SVG.
     *                                 Pass only on the Dashboard. Optional.
     *     @type array    $status      array('label' => '', 'state' => 'ok|warn|crit|neutral').
     *     @type array    $signals     List of array('value' => '', 'label' => '').
     *     @type array    $actions     List of array(
     *                                     'label'    => '',
     *                                     'url'      => '',
     *                                     'variant'  => 'primary|default|ghost',
     *                                     'external' => bool,
     *                                 ).
     * }
     * @return void
     */
    public static function page_header($args = array()) {
        $args = wp_parse_args($args, array(
            'title' => '',
            'description' => '',
            'brand' => null,
            'status' => array(),
            'signals' => array(),
            'actions' => array(),
        ));

        if ($args['title'] === '') {
            return;
        }

        $product = __('Geeky Bot', 'geeky-bot');

        // The product name sits above the page title on every screen except the
        // Dashboard, where the title already is the product name. Repeating it
        // there would just read as a stutter.
        $show_product_label = strcasecmp(trim($args['title']), $product) !== 0;
        ?>
        <div class="gb2-header">
            <?php if (is_callable($args['brand'])) : ?>
                <span class="gb2-header__brand" aria-hidden="true"><?php call_user_func($args['brand']); ?></span>
            <?php endif; ?>

            <div class="gb2-header__text">
                <?php if ($show_product_label) : ?>
                    <span class="gb2-header__product"><?php echo esc_html($product); ?></span>
                <?php endif; ?>

                <h1 class="gb2-header__title"><?php echo esc_html($args['title']); ?></h1>

                <?php if ($args['description'] !== '') : ?>
                    <p class="gb2-header__desc"><?php echo esc_html($args['description']); ?></p>
                <?php endif; ?>

                <?php if (!empty($args['signals'])) : ?>
                    <ul class="gb2-header__signals">
                        <?php foreach ($args['signals'] as $signal) :
                            $value = isset($signal['value']) ? (string) $signal['value'] : '';
                            $label = isset($signal['label']) ? (string) $signal['label'] : '';
                            if ($value === '' && $label === '') {
                                continue;
                            }
                            ?>
                            <li><b><?php echo esc_html($value); ?></b><?php echo esc_html($label); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php if (!empty($args['status']) || !empty($args['actions'])) : ?>
                <div class="gb2-header__actions">
                    <?php
                    if (!empty($args['status']['label'])) {
                        self::pill($args['status']['label'], isset($args['status']['state']) ? $args['status']['state'] : 'neutral');
                    }

                    foreach ($args['actions'] as $action) {
                        if (empty($action['label']) || empty($action['url'])) {
                            continue;
                        }
                        self::action_link($action);
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        /*
         * Required. WordPress relocates every admin notice with:
         *
         *   $headerEnd = $( '.wp-header-end' );
         *   if ( ! $headerEnd.length ) { $headerEnd = $( '.wrap h1, .wrap h2' ).first(); }
         *   $( 'div.updated, div.error, div.notice' ).insertAfter( $headerEnd );
         *
         * That is a *descendant* selector, so without this marker it finds the
         * <h1> nested inside the header and injects notices into the middle of
         * the flex row, breaking the layout. The marker sends them below the
         * whole header instead. Core styles it `visibility: hidden`.
         */
        ?>
        <hr class="wp-header-end">
        <?php
    }

    /**
     * The GeekyBot mark.
     *
     * Lives with the design system rather than on the menu class, so the
     * Commerce Pro add-on can render the same identity instead of carrying a
     * second copy that drifts.
     *
     * @return void
     */
    public static function brand_mark() {
        static $instance = 0;
        $gid = 'gbBrandMark' . (++$instance);
        $g = 'url(#' . $gid . ')';
        ?>
        <svg class="gb2-brand-mark" viewBox="0 0 100 100" role="img" focusable="false" aria-hidden="true">
            <defs><linearGradient id="<?php echo esc_attr($gid); ?>" x1="8" y1="16" x2="92" y2="88" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#2f6bed" /><stop offset="1" stop-color="#7c3aed" /></linearGradient></defs>
            <g transform="translate(0,8)">
                <path d="M20 15 C21 22 23 24 30 25 C23 26 21 28 20 35 C19 28 17 26 10 25 C17 24 19 22 20 15 Z" fill="<?php echo esc_attr($g); ?>" />
                <path d="M55 20 H73 Q88 20 88 35 V45 Q88 60 73 60 L70 60 L73 72 L62 60 H55 Q40 60 40 45 V35 Q40 20 55 20 Z" fill="none" stroke="<?php echo esc_attr($g); ?>" stroke-width="5.5" stroke-linejoin="round" />
                <line x1="66" y1="20" x2="66" y2="13" stroke="<?php echo esc_attr($g); ?>" stroke-width="3.5" stroke-linecap="round" /><circle cx="66" cy="10" r="3" fill="<?php echo esc_attr($g); ?>" />
                <rect x="51" y="31" width="26" height="16" rx="7" fill="<?php echo esc_attr($g); ?>" />
                <ellipse cx="59" cy="39" rx="2.3" ry="3" fill="#fff" /><ellipse cx="69" cy="39" rx="2.3" ry="3" fill="#fff" />
                <circle cx="58" cy="53" r="1.7" fill="<?php echo esc_attr($g); ?>" /><circle cx="64" cy="53" r="1.7" fill="<?php echo esc_attr($g); ?>" /><circle cx="70" cy="53" r="1.7" fill="<?php echo esc_attr($g); ?>" />
                <path d="M20 46 L48 46 L44 60 L27 60 Z" fill="none" stroke="<?php echo esc_attr($g); ?>" stroke-width="4.5" stroke-linejoin="round" />
                <path d="M20 46 L15 40 L11 40" fill="none" stroke="<?php echo esc_attr($g); ?>" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round" />
                <line x1="29" y1="60" x2="29" y2="63" stroke="<?php echo esc_attr($g); ?>" stroke-width="4" /><line x1="42" y1="60" x2="42" y2="63" stroke="<?php echo esc_attr($g); ?>" stroke-width="4" />
                <circle cx="29" cy="66" r="3" fill="<?php echo esc_attr($g); ?>" /><circle cx="42" cy="66" r="3" fill="<?php echo esc_attr($g); ?>" />
            </g>
        </svg>
        <?php
    }

    /**
     * Single header/toolbar link styled as a button.
     *
     * @param array $action Action definition. See page_header().
     * @return void
     */
    public static function action_link($action) {
        $variant = isset($action['variant']) ? $action['variant'] : 'default';
        $classes = 'gb2-btn';

        if ($variant === 'primary') {
            $classes .= ' gb2-btn--primary';
        } elseif ($variant === 'ghost') {
            $classes .= ' gb2-btn--ghost';
        }

        $external = !empty($action['external']);
        ?>
        <a class="<?php echo esc_attr($classes); ?>"
           href="<?php echo esc_url($action['url']); ?>"
            <?php if ($external) : ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>>
            <?php echo esc_html($action['label']); ?>
            <?php if ($external) : ?>
                <span class="gb2-screen-reader-text"><?php esc_html_e('(opens in a new tab)', 'geeky-bot'); ?></span>
            <?php endif; ?>
        </a>
        <?php
    }

    /**
     * Status pill. State is semantic, never decorative.
     *
     * @param string $label Visible text.
     * @param string $state ok|warn|crit|neutral.
     * @param bool   $dot   Whether to show the leading dot.
     * @return void
     */
    public static function pill($label, $state = 'neutral', $dot = true) {
        $allowed = array('ok', 'warn', 'crit', 'neutral');
        $state = in_array($state, $allowed, true) ? $state : 'neutral';
        $class = 'gb2-pill';

        if ($state !== 'neutral') {
            $class .= ' gb2-pill--' . $state;
        }
        ?>
        <span class="<?php echo esc_attr($class); ?>">
            <?php if ($dot) : ?><span class="gb2-pill__dot" aria-hidden="true"></span><?php endif; ?>
            <?php echo esc_html($label); ?>
        </span>
        <?php
    }

    /**
     * Open a card. Always pair with card_close().
     *
     * @param string $title Card heading. Optional — omit for a headless card.
     * @param string $meta  Right-aligned secondary text. Optional.
     * @param bool   $flush Remove body padding (for flush tables and lists).
     * @param string $extra Extra classes for the card element.
     * @return void
     */
    public static function card_open($title = '', $meta = '', $flush = false, $extra = '') {
        $class = 'gb2-card' . ($extra !== '' ? ' ' . $extra : '');
        ?>
        <div class="<?php echo esc_attr($class); ?>">
            <?php if ($title !== '') : ?>
                <div class="gb2-card__head">
                    <h2><?php echo esc_html($title); ?></h2>
                    <?php if ($meta !== '') : ?>
                        <span class="gb2-card__meta"><?php echo esc_html($meta); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="gb2-card__body<?php echo $flush ? ' gb2-card__body--flush' : ''; ?>">
        <?php
    }

    /**
     * Close a card opened with card_open().
     *
     * @return void
     */
    public static function card_close() {
        echo '</div></div>';
    }

    /**
     * Metric strip. Replaces the oversized stat cards.
     *
     * @param array $metrics List of array(
     *                           'label' => '',
     *                           'value' => '',
     *                           'delta' => '',
     *                           'direction' => 'up|down|flat',
     *                           'base' => '',
     *                       ).
     * @return void
     */
    public static function metrics($metrics) {
        if (empty($metrics)) {
            return;
        }
        ?>
        <div class="gb2-metrics">
            <?php foreach ($metrics as $metric) :
                $direction = isset($metric['direction']) ? $metric['direction'] : 'flat';
                $delta_class = 'gb2-metric__delta';

                if ($direction === 'up') {
                    $delta_class .= ' gb2-metric__delta--up';
                } elseif ($direction === 'down') {
                    $delta_class .= ' gb2-metric__delta--down';
                }
                ?>
                <div class="gb2-metric">
                    <span class="gb2-metric__label"><?php echo esc_html($metric['label']); ?></span>
                    <span class="gb2-metric__value">
                        <?php echo esc_html($metric['value']); ?>
                        <?php if (!empty($metric['delta'])) : ?>
                            <em class="<?php echo esc_attr($delta_class); ?>"><?php echo esc_html($metric['delta']); ?></em>
                        <?php endif; ?>
                    </span>
                    <?php if (!empty($metric['base'])) : ?>
                        <span class="gb2-metric__base"><?php echo esc_html($metric['base']); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Single row in a "needs attention" list.
     *
     * @param array $task {
     *     @type string $title       Required.
     *     @type string $description Optional.
     *     @type string $severity    high|medium|critical|done.
     *     @type array  $action      array('label' => '', 'url' => '') — optional.
     *     @type array  $status      array('label' => '', 'state' => '') — shown when no action.
     * }
     * @return void
     */
    public static function task($task) {
        $task = wp_parse_args($task, array(
            'title' => '',
            'description' => '',
            'severity' => 'medium',
            'action' => array(),
            'status' => array(),
        ));

        if ($task['title'] === '') {
            return;
        }

        $sev_class = 'gb2-task__sev';

        if ($task['severity'] === 'high') {
            $sev_class .= ' gb2-task__sev--high';
        } elseif ($task['severity'] === 'critical') {
            $sev_class .= ' gb2-task__sev--critical';
        } elseif ($task['severity'] === 'done') {
            $sev_class .= ' gb2-task__sev--done';
        }
        ?>
        <li class="gb2-task">
            <span class="<?php echo esc_attr($sev_class); ?>" aria-hidden="true"></span>
            <span class="gb2-task__text">
                <strong><?php echo esc_html($task['title']); ?></strong>
                <?php if ($task['description'] !== '') : ?>
                    <span><?php echo esc_html($task['description']); ?></span>
                <?php endif; ?>
            </span>
            <?php
            $has_action = !empty($task['action']['label']) && !empty($task['action']['url']);
            $has_status = !empty($task['status']['label']);
            ?>
            <?php if ($has_action || $has_status) : ?>
                <span class="gb2-task__action">
                    <?php
                    // Both may appear: a finished step still needs to be
                    // revisitable, so its state pill does not replace its link.
                    if ($has_status) {
                        self::pill($task['status']['label'], isset($task['status']['state']) ? $task['status']['state'] : 'neutral');
                    }
                    if ($has_action) {
                        self::action_link($task['action']);
                    }
                    ?>
                </span>
            <?php endif; ?>
        </li>
        <?php
    }

    /**
     * Labelled band divider, used to give long pages a reading order.
     *
     * @param string $label Band label.
     * @return void
     */
    public static function rule($label) {
        ?>
        <div class="gb2-rule"><b><?php echo esc_html($label); ?></b></div>
        <?php
    }

    /**
     * Guided setup panel.
     *
     * Renders only while setup is incomplete, and is meant to sit above
     * everything else: on a new store the metrics and chart are all empty, so
     * the only useful thing on the page is what to do next. The first
     * unfinished step is promoted as the current one and is the sole primary
     * action, so there is never a question about where to click.
     *
     * @param array $steps List of array(
     *                         'title'       => '',
     *                         'description' => '',   what the step does
     *                         'consequence' => '',   what breaks if skipped
     *                         'done'        => bool,
     *                         'action'      => array('label' => '', 'url' => ''),
     *                     ).
     * @return void
     */
    public static function setup_guide($steps) {
        $steps = array_values((array) $steps);

        if (empty($steps)) {
            return;
        }

        $done = 0;
        foreach ($steps as $step) {
            if (!empty($step['done'])) {
                $done++;
            }
        }

        // Nothing to guide once every step is finished.
        if ($done === count($steps)) {
            return;
        }

        $current_index = null;
        foreach ($steps as $index => $step) {
            if (empty($step['done'])) {
                $current_index = $index;
                break;
            }
        }
        ?>
        <section class="gb2-setup" aria-label="<?php esc_attr_e('Finish setting up Geeky Bot', 'geeky-bot'); ?>">
            <div class="gb2-setup__head">
                <div>
                    <h2><?php esc_html_e('Finish setting up Geeky Bot', 'geeky-bot'); ?></h2>
                    <p><?php esc_html_e('The assistant is not ready for shoppers yet. Work through these steps in order — each one takes a minute.', 'geeky-bot'); ?></p>
                </div>
                <span class="gb2-setup__count">
                    <?php
                    printf(
                        /* translators: 1: number of finished steps, 2: total number of steps. */
                        esc_html__('%1$s of %2$s done', 'geeky-bot'),
                        esc_html(number_format_i18n($done)),
                        esc_html(number_format_i18n(count($steps)))
                    );
                    ?>
                </span>
            </div>

            <ol class="gb2-setup__steps">
                <?php foreach ($steps as $index => $step) :
                    $is_done = !empty($step['done']);
                    $is_current = ($index === $current_index);
                    $state = $is_done ? 'done' : ($is_current ? 'current' : 'todo');
                    ?>
                    <li class="gb2-setup__step gb2-setup__step--<?php echo esc_attr($state); ?>">
                        <span class="gb2-setup__marker" aria-hidden="true"><?php
                            echo $is_done ? '&#10003;' : esc_html(number_format_i18n($index + 1)); ?></span>

                        <div class="gb2-setup__body">
                            <h3>
                                <?php echo esc_html($step['title']); ?>
                                <?php if ($is_current) : ?>
                                    <em class="gb2-setup__now"><?php esc_html_e('Do this next', 'geeky-bot'); ?></em>
                                <?php endif; ?>
                            </h3>

                            <?php if (!empty($step['description'])) : ?>
                                <p><?php echo esc_html($step['description']); ?></p>
                            <?php endif; ?>

                            <?php if (!$is_done && !empty($step['consequence'])) : ?>
                                <p class="gb2-setup__risk"><?php echo esc_html($step['consequence']); ?></p>
                            <?php endif; ?>
                        </div>

                        <?php if (!$is_done && !empty($step['action']['label']) && !empty($step['action']['url'])) : ?>
                            <span class="gb2-setup__action">
                                <?php
                                $action = $step['action'];
                                // Only the current step gets the primary button, so the
                                // page has exactly one obvious next click.
                                $action['variant'] = $is_current ? 'primary' : 'default';
                                self::action_link($action);
                                ?>
                            </span>
                        <?php elseif ($is_done) : ?>
                            <span class="gb2-setup__action">
                                <?php self::pill(__('Done', 'geeky-bot'), 'ok'); ?>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
        <?php
    }

    /**
     * Time-series area chart, drawn as inline SVG.
     *
     * No charting library: the series is at most 90 points, so building the
     * path server-side avoids shipping a dependency into wp-admin.
     *
     * @param array  $series     Current period. List of array('date' => 'Y-m-d', 'value' => int).
     * @param array  $comparison Previous period, same length. Optional.
     * @param string $label      Accessible description of what is plotted.
     * @return void
     */
    public static function chart($series, $comparison = array(), $label = '') {
        $series = array_values((array) $series);
        $count = count($series);

        if ($count < 2) {
            self::empty_state(
                __('Not enough history yet', 'geeky-bot'),
                __('The chart appears once shoppers have used the assistant on at least two different days.', 'geeky-bot')
            );
            return;
        }

        $values = array();
        foreach ($series as $point) {
            $values[] = max(0, (int) $point['value']);
        }

        $comparison_values = array();
        foreach ((array) $comparison as $point) {
            $comparison_values[] = max(0, (int) $point['value']);
        }

        // A flat line along zero tells the admin nothing and looks like a bug.
        if (array_sum($values) === 0 && array_sum($comparison_values) === 0) {
            self::empty_state(
                __('No activity in this period', 'geeky-bot'),
                __('Once shoppers start using the assistant, their daily activity is plotted here.', 'geeky-bot')
            );
            return;
        }

        // Four gridlines, so the axis maximum is the smallest round step that
        // covers the peak when multiplied by four.
        $peak = max(array_merge($values, $comparison_values, array(1)));
        $max = self::axis_step($peak) * 4;

        $x0 = 40;
        $x1 = 632;
        $y0 = 12;
        $y1 = 176;

        $points = self::chart_points($values, $x0, $x1, $y0, $y1, $max);
        $line = 'M ' . implode(' L ', $points);
        $area = $line . ' L ' . $x1 . ' ' . $y1 . ' L ' . $x0 . ' ' . $y1 . ' Z';

        $comparison_line = '';
        if (count($comparison_values) === $count) {
            $comparison_line = 'M ' . implode(' L ', self::chart_points($comparison_values, $x0, $x1, $y0, $y1, $max));
        }

        $gradient_id = 'gb2ChartFill' . wp_rand(1000, 9999);

        // Label roughly six dates without crowding the axis.
        $tick_every = max(1, (int) ceil($count / 6));
        ?>
        <div class="gb2-chart">
            <svg class="gb2-chart__svg" viewBox="0 0 640 204" role="img"
                aria-label="<?php echo esc_attr($label !== '' ? $label : __('Daily assistant activity', 'geeky-bot')); ?>">
                <defs>
                    <linearGradient id="<?php echo esc_attr($gradient_id); ?>" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#5b3df5" stop-opacity=".20" />
                        <stop offset="100%" stop-color="#5b3df5" stop-opacity="0" />
                    </linearGradient>
                </defs>

                <?php for ($tick = 0; $tick <= 4; $tick++) :
                    $tick_value = (int) round($max * $tick / 4);
                    $tick_y = $y1 - (($y1 - $y0) * $tick / 4);
                    ?>
                    <line x1="<?php echo esc_attr($x0); ?>" y1="<?php echo esc_attr($tick_y); ?>"
                          x2="<?php echo esc_attr($x1); ?>" y2="<?php echo esc_attr($tick_y); ?>"
                          stroke="<?php echo $tick === 0 ? '#e2e4ea' : '#eef0f4'; ?>" stroke-width="1" />
                    <text class="gb2-chart__axis" x="<?php echo esc_attr($x0 - 8); ?>"
                          y="<?php echo esc_attr($tick_y + 3); ?>" text-anchor="end"><?php
                        echo esc_html(number_format_i18n($tick_value)); ?></text>
                <?php endfor; ?>

                <?php if ($comparison_line !== '') : ?>
                    <path d="<?php echo esc_attr($comparison_line); ?>" fill="none" stroke="#b9bdc8"
                          stroke-width="1.5" stroke-dasharray="4 3" />
                <?php endif; ?>

                <path d="<?php echo esc_attr($area); ?>" fill="url(#<?php echo esc_attr($gradient_id); ?>)" />
                <path d="<?php echo esc_attr($line); ?>" fill="none" stroke="#5b3df5" stroke-width="2"
                      stroke-linejoin="round" stroke-linecap="round" />

                <?php
                $last = explode(' ', $points[$count - 1]);
                ?>
                <circle cx="<?php echo esc_attr($last[0]); ?>" cy="<?php echo esc_attr($last[1]); ?>"
                        r="3.5" fill="#fff" stroke="#5b3df5" stroke-width="2" />

                <?php foreach ($series as $index => $point) :
                    if ($index % $tick_every !== 0 && $index !== $count - 1) {
                        continue;
                    }
                    $tx = $x0 + (($x1 - $x0) * $index / ($count - 1));
                    $stamp = strtotime((string) $point['date']);
                    ?>
                    <text class="gb2-chart__axis" x="<?php echo esc_attr(round($tx, 1)); ?>" y="196"
                          text-anchor="middle"><?php echo esc_html($stamp ? date_i18n('j M', $stamp) : ''); ?></text>
                <?php endforeach; ?>
            </svg>
        </div>
        <?php
    }

    /**
     * Map values onto the chart's plot area.
     *
     * @param array $values Numeric series.
     * @param int   $x0     Left edge.
     * @param int   $x1     Right edge.
     * @param int   $y0     Top edge.
     * @param int   $y1     Baseline.
     * @param int   $max    Axis maximum.
     * @return array List of "x y" strings.
     */
    private static function chart_points($values, $x0, $x1, $y0, $y1, $max) {
        $points = array();
        $count = count($values);
        $span = max(1, $count - 1);

        foreach ($values as $index => $value) {
            $x = $x0 + (($x1 - $x0) * $index / $span);
            $y = $max > 0 ? $y1 - (($y1 - $y0) * $value / $max) : $y1;
            $points[] = round($x, 1) . ' ' . round($y, 1);
        }

        return $points;
    }

    /**
     * Pick a round gridline step for a given peak value.
     *
     * The chart draws four divisions, so this returns the smallest friendly
     * step whose fourth multiple still covers the peak. A peak of 109 gives a
     * step of 30 (axis 0–120), not 50 (axis 0–200), which would waste half the
     * plot area on empty headroom.
     *
     * @param int $peak Highest plotted value.
     * @return int
     */
    private static function axis_step($peak) {
        $needed = $peak / 4;
        $candidates = array(1, 2, 3, 5, 10, 15, 20, 25, 30, 40, 50, 75, 100, 150, 200, 250, 300, 400, 500, 750, 1000, 1500, 2000, 2500, 5000);

        foreach ($candidates as $candidate) {
            if ($needed <= $candidate) {
                return $candidate;
            }
        }

        return 10000;
    }

    /**
     * Chart legend row.
     *
     * @param string $current    Label for the plotted series.
     * @param string $comparison Label for the dashed comparison. Optional.
     * @param string $note       Right-aligned note. Optional.
     * @return void
     */
    public static function chart_legend($current, $comparison = '', $note = '') {
        ?>
        <div class="gb2-chart__legend">
            <span><i class="gb2-chart__key"></i><?php echo esc_html($current); ?></span>
            <?php if ($comparison !== '') : ?>
                <span><i class="gb2-chart__key gb2-chart__key--ghost"></i><?php echo esc_html($comparison); ?></span>
            <?php endif; ?>
            <?php if ($note !== '') : ?>
                <span class="gb2-chart__note"><?php echo esc_html($note); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Conversion funnel.
     *
     * @param array $stages List of array('label' => '', 'value' => int, 'note' => '').
     * @return void
     */
    public static function funnel($stages) {
        $stages = array_values((array) $stages);

        if (empty($stages)) {
            return;
        }

        // A column of zeros is not a funnel. On a new store, say so instead.
        $total = 0;
        foreach ($stages as $stage) {
            $total += max(0, (int) $stage['value']);
        }

        if ($total === 0) {
            self::empty_state(
                __('No shopper activity yet', 'geeky-bot'),
                __('Once shoppers open the assistant, the steps they take through it are tracked here.', 'geeky-bot')
            );
            return;
        }

        // Bars are scaled against the largest stage, not the first. These are
        // event counts, not a strict subset: one conversation can open several
        // products, so a later stage legitimately exceeds an earlier one and
        // scaling against stage one would overflow the card.
        $largest = 1;
        $first = max(0, (int) $stages[0]['value']);
        foreach ($stages as $stage) {
            $largest = max($largest, (int) $stage['value']);
        }
        ?>
        <div class="gb2-funnel">
            <?php foreach ($stages as $index => $stage) :
                $value = max(0, (int) $stage['value']);
                $width = ($value / $largest) * 100;
                $share = $first > 0 ? ($value / $first) * 100 : 0;

                if ($index > 0) {
                    $previous = max(0, (int) $stages[$index - 1]['value']);
                    // Only meaningful when the step actually narrows.
                    if ($previous > 0 && $value <= $previous) {
                        $carried = round(($value / $previous) * 100);
                        ?>
                        <p class="gb2-funnel__drop">
                            <?php
                            printf(
                                /* translators: %s: percentage of shoppers who continued to this step. */
                                esc_html__('%s%% continued', 'geeky-bot'),
                                esc_html(number_format_i18n($carried))
                            );
                            ?>
                        </p>
                    <?php }
                } ?>

                <div class="gb2-funnel__stage">
                    <span class="gb2-funnel__bar" style="width: <?php echo esc_attr(round($width, 1)); ?>%"></span>
                    <span class="gb2-funnel__text">
                        <span class="gb2-funnel__label"><?php echo esc_html($stage['label']); ?></span>
                        <span class="gb2-funnel__value"><?php echo esc_html(number_format_i18n($value)); ?></span>
                        <span class="gb2-funnel__share"><?php echo esc_html(number_format_i18n(round($share))); ?>%</span>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Ranked horizontal bars, for breakdowns like failure reasons.
     *
     * @param array $bars List of array('label' => '', 'value' => int, 'tone' => 'accent|warn|crit|mute').
     * @return void
     */
    public static function bars($bars) {
        $bars = array_values((array) $bars);

        if (empty($bars)) {
            return;
        }

        $top = 0;
        foreach ($bars as $bar) {
            $top = max($top, (int) $bar['value']);
        }
        $top = max(1, $top);
        ?>
        <div class="gb2-bars">
            <?php foreach ($bars as $bar) :
                $value = max(0, (int) $bar['value']);
                $tone = isset($bar['tone']) ? $bar['tone'] : 'accent';
                $width = round(($value / $top) * 100, 1);
                ?>
                <div class="gb2-bar">
                    <div class="gb2-bar__top">
                        <span class="gb2-bar__label"><?php echo esc_html($bar['label']); ?></span>
                        <span class="gb2-bar__value"><?php echo esc_html(number_format_i18n($value)); ?></span>
                    </div>
                    <div class="gb2-bar__track">
                        <div class="gb2-bar__fill gb2-bar__fill--<?php echo esc_attr($tone); ?>"
                             style="width: <?php echo esc_attr($width); ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Recent activity feed row.
     *
     * @param array $item {
     *     @type string $text  Primary line. Required.
     *     @type string $meta  Secondary line.
     *     @type string $when  Right-aligned relative time.
     *     @type string $tone  ask|cart|miss.
     * }
     * @return void
     */
    public static function feed_item($item) {
        $item = wp_parse_args($item, array(
            'text' => '',
            'meta' => '',
            'when' => '',
            'tone' => 'ask',
        ));

        if ($item['text'] === '') {
            return;
        }

        $glyphs = array('ask' => '?', 'cart' => '+', 'miss' => '!');
        $tone = isset($glyphs[$item['tone']]) ? $item['tone'] : 'ask';
        ?>
        <li class="gb2-feed__item">
            <span class="gb2-feed__icon gb2-feed__icon--<?php echo esc_attr($tone); ?>" aria-hidden="true"><?php
                echo esc_html($glyphs[$tone]); ?></span>
            <span class="gb2-feed__text">
                <b><?php echo esc_html($item['text']); ?></b>
                <?php if ($item['meta'] !== '') : ?>
                    <span><?php echo esc_html($item['meta']); ?></span>
                <?php endif; ?>
            </span>
            <?php if ($item['when'] !== '') : ?>
                <span class="gb2-feed__when"><?php echo esc_html($item['when']); ?></span>
            <?php endif; ?>
        </li>
        <?php
    }

    /**
     * Empty state. Explains what is missing and what fills it.
     *
     * @param string $title Short statement.
     * @param string $body  What the admin can do about it.
     * @param array  $action Optional array('label' => '', 'url' => '').
     * @return void
     */
    public static function empty_state($title, $body = '', $action = array()) {
        ?>
        <div class="gb2-empty">
            <strong><?php echo esc_html($title); ?></strong>
            <?php if ($body !== '') : ?>
                <p><?php echo esc_html($body); ?></p>
            <?php endif; ?>
            <?php if (!empty($action['label']) && !empty($action['url'])) : ?>
                <p><?php self::action_link($action); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
}
