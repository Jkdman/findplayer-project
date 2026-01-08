<?php
if (!defined('ABSPATH')) exit;

class CE_Calendar {

    protected $month;
    protected $year;
    protected $events_by_date = [];

    public function __construct($month, $year) {
        $this->month = max(1, min(12, (int)$month));
        $this->year  = (int)$year;

        $this->load_events();
    }

    /**
     * Carica eventi tramite filtro WordPress
     * Altri plugin (es. Find Player) si agganciano al filtro 'ce_get_events'
     */
    protected function load_events() {
        $start_date = sprintf('%04d-%02d-01', $this->year, $this->month);
        $end_date   = date('Y-m-t', strtotime($start_date)); // ultimo giorno del mese

        $args = [
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'month'      => $this->month,
            'year'       => $this->year,
        ];

        /**
         * Formato atteso di ogni evento:
         * [
         *   'date'       => 'YYYY-MM-DD',
         *   'title'      => 'Titolo evento',
         *   'url'        => 'https://…',
         *   'discipline' => 'Calcio' (opzionale ma consigliata)
         * ]
         */
        $events = apply_filters('ce_get_events', [], $args);

        if (!is_array($events)) $events = [];

        foreach ($events as $event) {
            if (empty($event['date'])) continue;

            $date = $event['date'];

            $meta = $this->map_discipline_meta(
                isset($event['discipline']) ? $event['discipline'] : ''
            );

            $event['category'] = $meta['category'];
            $event['color']    = $meta['color'];
            $event['icon']     = $meta['icon'];

            if (!isset($this->events_by_date[$date])) {
                $this->events_by_date[$date] = [];
            }
            $this->events_by_date[$date][] = $event;
        }
    }

    /**
     * Mappa la disciplina su categoria / colore / icona
     */
    protected function map_discipline_meta($discipline) {
        $d = mb_strtolower(trim($discipline));

        // Default
        $meta = [
            'category' => 'altro',
            'color'    => '#9e9e9e',
            'icon'     => '📌',
        ];

// A) Pallone
$pallone = [
    'calcio',
    'basket',
    'pallavolo',
    'volley',
    'beach volley',
    'rugby',
    'calcetto',
    'paddle',
    'baseball',
    'ping pong',
];

// B) Arti marziali / combattimento
$combatt = [
    'aikido',
    'arti marziali',
    'jeet kune do',
    'karate',
    'judo',
    'kickboxing',
    'muay thai',
    'boxe',
    'pugilato',
    'm.m.a.',
    'difesa personale',
    'wing chun',
];

// C) Atletica / Fitness / Benessere
$atletica = [
    'corsa libera',
    'ginnastica libera',
    'body building',
    'running',
    'crossfit',
    'atletica',
    'yoga',
    'fitness',
    'skateboard',
];

// D) Ballo
$ballo = [
    'danza',
    'ballo',
    'balli di gruppo',
    'zumba',
    'hip hop',
];

// E) Acquatici
$acqua = [
    'nuoto',
    'pallanuoto',
    'immersione',
    'snorkeling',
    'canoa',
    'kayak',
    'sup',
];

// F) Motori e Ciclismo
$motori = [
    'enduro',
    'motocross',
    'mountain bike',
    'ciclismo',
    'mototurismo',
];

// G) Giochi da tavolo / ruolo
$giochi = [
    'giochi da tavolo',
    'giochi di ruolo',
];

// H) Sparatutto
$sparatutto = [
    'softair',
    'paintball',
    'lasertag',
    'sparatutto-online',
];

// I) Escursionismo / Orienteering
$esc = [
    'escursionismo',
    'trekking',
    'orienteering',
    'hiking',
    'nordic walking',
];


    // MATCHING CATEGORIA
    if (in_array($d, $pallone)) {
        return ['category' => 'pallone', 'color' => '#ff8c00', 'icon' => '⚽'];
    }
    if (in_array($d, $combatt)) {
        return ['category' => 'combattimento', 'color' => '#e63946', 'icon' => '🥋'];
    }
    if (in_array($d, $atletica)) {
        return ['category' => 'atletica', 'color' => '#1e90ff', 'icon' => '🏃‍♂️'];
    }
    if (in_array($d, $ballo)) {
        return ['category' => 'ballo', 'color' => '#ff4da6', 'icon' => '💃'];
    }
    if (in_array($d, $acqua)) {
        return ['category' => 'acquatici', 'color' => '#00bcd4', 'icon' => '🌊'];
    }
    if (in_array($d, $motori)) {
        return ['category' => 'motori', 'color' => '#f1c40f', 'icon' => '🏍️'];
    }
    if (in_array($d, $giochi)) {
        return ['category' => 'giochi', 'color' => '#8d6e63', 'icon' => '🎲'];
    }
    if (in_array($d, $sparatutto)) {
        return ['category' => 'sparatutto', 'color' => '#424242', 'icon' => '🔫'];
    }
    if (in_array($d, $esc)) {
        return ['category' => 'escursionismo', 'color' => '#795548', 'icon' => '🥾'];
    }

    return $meta;
}
    /**
     * Renderizza il calendario HTML
     */
    public function render() {
        $month_name = date_i18n('F Y', strtotime(sprintf('%04d-%02d-01', $this->year, $this->month)));

        $first_day_ts = strtotime(sprintf('%04d-%02d-01', $this->year, $this->month));
        $days_in_month = (int) date('t', $first_day_ts);
        $start_weekday = (int) date('N', $first_day_ts); // 1 (lun) - 7 (dom)

        // Per navigazione mese precedente/successivo
        $prev_month_ts = strtotime('-1 month', $first_day_ts);
        $next_month_ts = strtotime('+1 month', $first_day_ts);

        $prev_month = (int) date('n', $prev_month_ts);
        $prev_year  = (int) date('Y', $prev_month_ts);
        $next_month = (int) date('n', $next_month_ts);
        $next_year  = (int) date('Y', $next_month_ts);

        $base_url = remove_query_arg(['ce_month', 'ce_year']);

        ob_start();
        ?>
        <div class="ce-calendar-wrapper">
            <div class="ce-calendar-header">
                <a class="ce-nav ce-prev" href="<?php echo esc_url(add_query_arg([
                    'ce_month' => $prev_month,
                    'ce_year'  => $prev_year,
                ], $base_url)); ?>">«</a>

                <div class="ce-month-title">
                    <?php echo esc_html($month_name); ?>
                </div>

                <a class="ce-nav ce-next" href="<?php echo esc_url(add_query_arg([
                    'ce_month' => $next_month,
                    'ce_year'  => $next_year,
                ], $base_url)); ?>">»</a>
            </div>

            <table class="ce-calendar-table">
                <thead>
                    <tr>
                        <th>Lun</th>
                        <th>Mar</th>
                        <th>Mer</th>
                        <th>Gio</th>
                        <th>Ven</th>
                        <th>Sab</th>
                        <th>Dom</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $day = 1;
                    $cell = 1;

                    while ($day <= $days_in_month) {
                        echo '<tr>';

                        for ($col = 1; $col <= 7; $col++, $cell++) {
                            if ($cell < $start_weekday || $day > $days_in_month) {
                                echo '<td class="ce-empty"></td>';
                                continue;
                            }

                            $date_str = sprintf('%04d-%02d-%02d', $this->year, $this->month, $day);
                            $has_events = !empty($this->events_by_date[$date_str]);

                            $classes = ['ce-day'];
                            if ($has_events) $classes[] = 'has-events';

                            echo '<td class="' . esc_attr(implode(' ', $classes)) . '" data-date="' . esc_attr($date_str) . '">';
                            echo '<div class="ce-day-number">' . esc_html($day) . '</div>';

                            if ($has_events) {
                                echo '<div class="ce-dots">';
                                foreach ($this->events_by_date[$date_str] as $event) {
                                    $cat_class = 'ce-cat-' . sanitize_html_class($event['category']);
                                    echo '<span class="ce-dot ' . esc_attr($cat_class) . '" title="' . esc_attr($event['title']) . '"></span>';
                                }
                                echo '</div>';

                                echo '<div class="ce-day-popup">';
                                echo '<div class="ce-popup-inner">';
                                echo '<div class="ce-popup-header">';
                                echo '<span class="ce-popup-date">' . esc_html(date_i18n('d F Y', strtotime($date_str))) . '</span>';
                                echo '<button type="button" class="ce-popup-close">&times;</button>';
                                echo '</div>';
                                echo '<div class="ce-popup-body">';

                                foreach ($this->events_by_date[$date_str] as $event) {
                                    $icon = !empty($event['icon']) ? $event['icon'] : '📌';
                                    $title = esc_html($event['title']);
                                    $url = !empty($event['url']) ? esc_url($event['url']) : '#';

                                    echo '<a class="ce-event-item" href="' . $url . '">';
                                    echo '<span class="ce-event-icon">' . esc_html($icon) . '</span>';
                                    echo '<span class="ce-event-title">' . $title . '</span>';
                                    if (!empty($event['discipline'])) {
                                        echo '<span class="ce-event-discipline">' . esc_html($event['discipline']) . '</span>';
                                    }
                                    echo '</a>';
                                }

                                echo '</div>'; // body
                                echo '</div>'; // inner
                                echo '</div>'; // popup
                            }

                            echo '</td>';
                            $day++;
                        }

                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}