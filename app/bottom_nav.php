<?php
// ============================================================================
// BARRA DE NAVEGAÇÃO INFERIOR DO APLICATIVO
// ============================================================================
// Componente visual compartilhado por dashboard.php, parceiros.php, ativar.php,
// perfil.php e dependentes.php.
//
// Uso:
//   require_once __DIR__ . '/bottom_nav.php';
//   app_bottom_nav([
//       ['href' => 'dashboard.php', 'icone' => 'home', 'label' => 'Home', 'ativo' => true],
//       ['href' => 'parceiros.php', 'icone' => 'storefront', 'label' => 'Parceiros'],
//       ['tipo' => 'button', 'onclick' => 'abrirAtendimento()', 'icone' => 'support_agent', 'label' => 'Atendimento'],
//   ]);
//
// Regras:
//  - Não altera destinos, nomes, onclick ou qualquer comportamento existente.
//  - Apenas apresentação: pill flutuante, ícone ativo em gradiente, safe area,
//    áreas de toque >= 48px e transições discretas.
// ============================================================================

if (!function_exists('app_bottom_nav')) {
    function app_bottom_nav(array $itens): void
    {
        static $cssImpresso = false;

        if (!$cssImpresso) {
            $cssImpresso = true;
            ?>
<style>
    /* Bottom navigation — visual de Web App / fintech */
    .appbn {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 60;
        padding: 10px 12px 12px;
        padding-bottom: calc(10px + env(safe-area-inset-bottom, 0px));
        pointer-events: none;
        background: linear-gradient(to top, rgba(248, 249, 250, 0.96) 20%, rgba(248, 249, 250, 0.72) 58%, rgba(248, 249, 250, 0));
        animation: appbnIn 0.35s ease both;
    }
    .appbn-inner {
        pointer-events: auto;
        width: 100%;
        max-width: 460px;
        margin: 0 auto;
        display: flex;
        align-items: stretch;
        gap: 2px;
        padding: 6px;
        border-radius: 26px;
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(81, 3, 109, 0.10);
        box-shadow: 0 20px 45px -24px rgba(25, 28, 29, 0.45), 0 2px 10px rgba(25, 28, 29, 0.05);
        -webkit-backdrop-filter: blur(20px) saturate(140%);
        backdrop-filter: blur(20px) saturate(140%);
    }
    .appbn-item {
        flex: 1 1 0;
        min-width: 0;
        min-height: 52px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 2px;
        padding: 4px 4px 3px;
        border: 0;
        border-radius: 20px;
        background: transparent;
        text-decoration: none;
        color: #6f6675;
        font-family: inherit;
        cursor: pointer;
        -webkit-tap-highlight-color: transparent;
        touch-action: manipulation;
        transition: background-color 0.22s ease, color 0.22s ease, transform 0.18s ease;
    }
    .appbn-item:hover { background: rgba(81, 3, 109, 0.05); }
    .appbn-item:active { transform: scale(0.96); }
    .appbn-item:focus-visible {
        outline: 2px solid #51036d;
        outline-offset: 2px;
    }
    .appbn-icon {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        font-size: 21px;
        line-height: 1;
        transition: background 0.24s ease, color 0.24s ease, box-shadow 0.24s ease, transform 0.24s ease;
    }
    .appbn-label {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 10.5px;
        line-height: 1;
        font-weight: 700;
        letter-spacing: 0.01em;
    }
    .appbn-item.is-active {
        background: rgba(81, 3, 109, 0.055);
        color: #51036d;
    }
    .appbn-item.is-active .appbn-icon {
        background: linear-gradient(135deg, #3c0352 0%, #51036d 55%, #7a2b9c 100%);
        color: #ffffff;
        box-shadow: 0 10px 20px -10px rgba(81, 3, 109, 0.7);
        transform: translateY(-1px);
    }
    .appbn-item.is-active .appbn-label {
        color: #51036d;
        font-weight: 800;
    }
    @keyframes appbnIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .dark .appbn {
        background: linear-gradient(to top, rgba(18, 17, 21, 0.94) 20%, rgba(18, 17, 21, 0.70) 58%, rgba(18, 17, 21, 0));
    }
    .dark .appbn-inner {
        background: rgba(28, 26, 32, 0.92);
        border-color: rgba(255, 255, 255, 0.10);
    }
    .dark .appbn-item { color: #cfc7d4; }
    .dark .appbn-item:hover { background: rgba(255, 255, 255, 0.06); }
    .dark .appbn-item.is-active {
        background: rgba(182, 245, 112, 0.10);
        color: #d9f7b0;
    }
    .dark .appbn-item.is-active .appbn-label { color: #d9f7b0; }
    @media (max-width: 360px) {
        .appbn { padding-left: 8px; padding-right: 8px; }
        .appbn-item { padding-left: 2px; padding-right: 2px; }
        .appbn-icon { width: 32px; height: 32px; font-size: 20px; }
        .appbn-label { font-size: 10px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .appbn { animation: none !important; }
        .appbn-item, .appbn-icon { transition: none !important; }
    }
</style>
            <?php
        }

        echo '<nav class="appbn" aria-label="Navegação principal"><div class="appbn-inner">';
        foreach ($itens as $item) {
            $ativo = !empty($item['ativo']);
            $tipo = $item['tipo'] ?? 'link';
            $icone = htmlspecialchars((string)($item['icone'] ?? 'circle'), ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars((string)($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $classes = 'appbn-item' . ($ativo ? ' is-active' : '');

            if ($tipo === 'button') {
                $onclick = htmlspecialchars((string)($item['onclick'] ?? ''), ENT_QUOTES, 'UTF-8');
                echo '<button type="button" class="' . $classes . '" onclick="' . $onclick . '">';
            } else {
                $href = htmlspecialchars((string)($item['href'] ?? '#'), ENT_QUOTES, 'UTF-8');
                echo '<a class="' . $classes . '" href="' . $href . '"' . ($ativo ? ' aria-current="page"' : '') . '>';
            }

            echo '<span class="appbn-icon material-symbols-outlined"' . ($ativo ? ' style="font-variation-settings:\'FILL\' 1;"' : '') . '>' . $icone . '</span>';
            echo '<span class="appbn-label">' . $label . '</span>';

            echo $tipo === 'button' ? '</button>' : '</a>';
        }
        echo '</div></nav>';
    }
}
