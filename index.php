<?php
// Sprawdzamy, czy serwer odebrał żądanie metodą POST (to znaczy, że JS wysyła do nas dane do tłumaczenia)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ustawiamy nagłówek odpowiedzi na JSON, aby JS łatwo go odczytał
    header('Content-Type: application/json');

    // Pobieramy całą zawartość, którą wysłał do nas JS w formacie JSON
    $inputJSON = file_get_contents('php://input');
    // Rozkodowujemy JSON do postaci wbudowanej tablicy (array) w PHP
    $input = json_decode($inputJSON, TRUE);

    // Pobieramy przesłane wartości używając operatora null coalescing (jeśli puste, zwraca pusty string)
    $apiKey = $input['api_key'] ?? '';
    $text = $input['text'] ?? '';
    $srcLang = $input['src_lang'] ?? 'PL';
    $targetLang = $input['target_lang'] ?? 'EN';

    // Zabezpieczenie przed pustym kluczem lub tekstem w żądaniu
    if (empty($apiKey) || empty($text)) {
        echo json_encode(['error' => 'Brak klucza API lub tekstu.']);
        exit;
    }

    // Wykrywanie czy klucz wklejony przez użytkownika kończy się na ":fx" - to oznacza bezpłatny klucz API DeepL Dev
    $isFreeKey = str_ends_with(trim($apiKey), ':fx');
    // API darmowe (free) używa innej domeny niż API płatne (pro)
    $apiUrl = $isFreeKey
        ? 'https://api-free.deepl.com/v2/translate'
        : 'https://api.deepl.com/v2/translate';

    // Tworzenie gotowego łańcucha parametrów do żądania w klasycznym formacie na wzór formularza
    $postData = http_build_query([
        'text' => $text,
        'target_lang' => strtoupper($targetLang), // DeepL oczekuje dużych znaków
        'source_lang' => strtoupper($srcLang)
    ]);

    // Inicjalizacja strumienia dla cURL - narzędzia do wysyłki żądań HTTP miedzy serwerami w PHP
    $ch = curl_init();
    // Ustawienie adresu docelowego (API DeepL)
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    // Definiujemy, że będziemy wysyłać metodą POST
    curl_setopt($ch, CURLOPT_POST, true);
    // Dołączamy ciąg zawierający nasze zapytanie (text, sourceLang, targetLang)
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    // Dołączamy wymagane nagłówki: klucz API oraz odpowiedni Content-Type dla danych formularzowych
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: DeepL-Auth-Key " . trim($apiKey),
        "Content-Type: application/x-www-form-urlencoded"
    ]);
    // Ustawienie zwracania odpowiedzi cURL do zmiennej (zamiast wypisywania na ekran)
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Uruchomienie połączenia z serwerem DeepL i pobranie z niego wyniku do zmiennej response
    $response = curl_exec($ch);
    // Przechwycenie ewentualnych błędów z systemu cURL na wypadek braku internetu u hosta
    $error = curl_error($ch);
    // Pobranie kodu HTTP otrzymanego od DeepL (200 oznacza sukces, 403 błąd auth itd.)
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // Zamykamy sesję cURL w celu zwolnienia pamięci
    curl_close($ch);

    // Wyłapanie absolutnego błędu lokalnego wykonania
    if ($error) {
        echo json_encode(['error' => 'Błąd serwera (cURL): ' . $error]);
        exit;
    }

    // Dekodujemy zawartość otrzymaną od API DeepL do tablicy JSON
    $resData = json_decode($response, true);
    // Jeśli kod odebrany z zewnątrz jest inny niż 200 (na przykład np. 403 dla nieważnego klucza)
    if ($httpCode !== 200 || isset($resData['message'])) {
        echo json_encode(['error' => 'Błąd z DeepL (' . $httpCode . '): ' . ($resData['message'] ?? 'Nieznany')]);
        exit;
    }

    // Gdy w zwrocie tablica zawiera klucz 'translations', odzyskujemy i wysyłamy samo tłumaczenie z jego tekstu
    if (isset($resData['translations'][0]['text'])) {
        echo json_encode(['translation' => $resData['translations'][0]['text']]);
    } else {
        echo json_encode(['error' => 'Brak oczekiwanego formatu odpowiedzi powrotnej z DeepL.']);
    }

    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilingual Posting Tool</title>
    <link rel="icon" href="/favicon.ico" type="image/x-icon" />

    <style>
        :root {
            --bg-grad-1: #fdfbfb;
            --bg-grad-2: #ebedee;
            --container-bg: rgba(255, 255, 255, 0.7);
            --container-border: rgba(255, 255, 255, 0.5);
            --text-color: #2c3e50;
            --text-color-muted: #576574;
            --input-bg: rgba(255, 255, 255, 0.9);
            --input-border: #d1d8e0;
            --input-focus: #4b7bec;
            --btn-bg: #4b7bec;
            --btn-hover: #3867d6;
            --btn-text: #fff;
            --cookie-bg: #ffffff;
            --cookie-text: #2f3640;
            --shadow-color: rgba(0, 0, 0, 0.05);
            --footer-bg: #f1f2f6;
            --lang-dropdown-bg: #fff;
            --lang-dropdown-hover: #f1f2f6;
        }

        [data-theme="dark"] {
            --bg-grad-1: #1e1e24;
            --bg-grad-2: #2b2b36;
            --container-bg: rgba(43, 43, 54, 0.75);
            --container-border: rgba(87, 101, 116, 0.3);
            --text-color: #f1f2f6;
            --text-color-muted: #a4b0be;
            --input-bg: rgba(30, 30, 36, 0.9);
            --input-border: #576574;
            --input-focus: #4b7bec;
            --btn-bg: #4b7bec;
            --btn-hover: #3867d6;
            --cookie-bg: #2f3640;
            --cookie-text: #f1f2f6;
            --shadow-color: rgba(0, 0, 0, 0.4);
            --footer-bg: #1e1e24;
            --lang-dropdown-bg: #2f3640;
            --lang-dropdown-hover: #3d4651;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, var(--bg-grad-1) 0%, var(--bg-grad-2) 100%);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: background 0.3s ease, color 0.3s ease;
            box-sizing: border-box;
            padding: 40px 20px;
        }

        * {
            box-sizing: inherit;
        }

        .top-controls {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 100;
        }

        .top-btn {
            background: rgba(128, 128, 128, 0.1);
            border: 1px solid var(--container-border);
            border-radius: 50%;
            width: 45px;
            height: 45px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
            transition: all 0.2s ease;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .top-btn:hover {
            background: rgba(128, 128, 128, 0.2);
        }

        .top-btn svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        .container {
            width: 100%;
            max-width: 600px;
            padding: 40px;
            margin-top: 60px;
            background: var(--container-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--container-border);
            border-radius: 20px;
            box-shadow: 0 10px 30px var(--shadow-color);
            transition: all 0.3s ease;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            margin: 0;
            font-weight: 600;
            font-size: 2rem;
            letter-spacing: -0.5px;
        }

        .header p {
            margin: 8px 0 0;
            color: var(--text-color-muted);
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .flex-group {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .flex-group>div {
            flex: 1;
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        input[type="text"],
        input[type="password"],
        textarea,
        .custom-select-trigger {
            width: 100%;
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid var(--input-border);
            background: var(--input-bg);
            color: var(--text-color);
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 1rem;
            transition: all 0.2s ease;
            outline: none;
            cursor: text;
        }

        input:focus,
        textarea:focus,
        .custom-select-trigger.active {
            border-color: var(--input-focus);
            box-shadow: 0 0 0 3px rgba(75, 123, 236, 0.2);
        }

        textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.5;
        }

        .custom-select-trigger {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }

        .custom-options {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 5px;
            background: var(--lang-dropdown-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            box-shadow: 0 10px 30px var(--shadow-color);
            z-index: 50;
            display: none;
            flex-direction: column;
            max-height: 300px;
        }

        .custom-options.open {
            display: flex;
        }

        .search-lang {
            padding: 10px;
            border-bottom: 1px solid var(--input-border);
        }

        .search-lang input {
            padding: 8px 12px;
            font-size: 0.9rem;
            border-radius: 6px;
        }

        .options-list {
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .option-item {
            padding: 12px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .option-item:hover {
            background: var(--lang-dropdown-hover);
        }

        button.action-btn {
            width: 100%;
            padding: 14px 16px;
            border: none;
            border-radius: 12px;
            background: var(--btn-bg);
            color: var(--btn-text);
            font-family: system-ui, -apple-system, sans-serif;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        button.action-btn:hover {
            background: var(--btn-hover);
            transform: translateY(-1px);
        }

        button.action-btn:active {
            transform: translateY(1px);
        }

        button.action-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .loader {
            display: none;
            width: 18px;
            height: 18px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .output-group {
            display: none;
            margin-top: 30px;
            animation: fadeIn 0.4s ease forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #output_text {
            background: var(--bg-grad-2);
            cursor: text;
        }

        .btn-secondary {
            background: transparent;
            color: var(--text-color);
            border: 1px solid var(--input-border);
            margin-top: 10px;
        }

        .btn-secondary:hover {
            background: rgba(128, 128, 128, 0.05);
            color: var(--text-color);
        }

        .cookie-banner {
            position: fixed;
            bottom: -100%;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 600px;
            background: var(--cookie-bg);
            color: var(--cookie-text);
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 10px 40px var(--shadow-color);
            border: 1px solid var(--container-border);
            z-index: 1000;
            transition: bottom 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .cookie-banner.show {
            bottom: 30px;
        }

        .cookie-banner p {
            margin: 0;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .cookie-buttons {
            display: flex;
            gap: 12px;
        }

        .cookie-btn {
            flex: 1;
            padding: 10px 16px;
            border-radius: 12px;
            border: none;
            background: var(--btn-bg);
            color: var(--btn-text);
            cursor: pointer;
            font-family: system-ui, -apple-system, sans-serif;
            font-weight: 600;
        }

        .cookie-btn-decline {
            background: transparent;
            color: var(--cookie-text);
            border: 1px solid var(--input-border);
        }

        .cookie-btn-decline:hover {
            background: rgba(128, 128, 128, 0.1);
        }

        .tooltip-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .info-icon {
            fill: var(--text-color-muted);
            cursor: help;
            transition: fill 0.2s;
        }

        .tooltip-wrapper:hover .info-icon {
            fill: var(--input-focus);
        }

        .tooltip-box {
            visibility: hidden;
            opacity: 0;
            position: absolute;
            bottom: calc(100% + 5px);
            right: 0;
            width: 250px;
            background: var(--cookie-bg);
            border: 1px solid var(--container-border);
            box-shadow: 0 5px 20px var(--shadow-color);
            padding: 14px;
            border-radius: 12px;
            font-size: 0.85rem;
            line-height: 1.4;
            color: var(--cookie-text);
            transition: all 0.25s ease;
            z-index: 200;
            pointer-events: none;
        }

        .tooltip-box::after {
            content: "";
            position: absolute;
            top: 100%;
            right: 6px;
            border-width: 6px;
            border-style: solid;
            border-color: var(--cookie-bg) transparent transparent transparent;
        }

        .tooltip-box::before {
            content: "";
            position: absolute;
            top: 100%;
            left: -20px;
            right: -20px;
            height: 25px;
            background: transparent;
        }

        .tooltip-wrapper:hover .tooltip-box,
        .tooltip-box:hover {
            visibility: visible;
            opacity: 1;
            pointer-events: auto;
        }

        .tooltip-box a {
            color: var(--input-focus);
            font-weight: 600;
            text-decoration: none;
        }

        .tooltip-box a:hover {
            text-decoration: underline;
        }

        .error-message {
            color: #e74c3c;
            background: rgba(231, 76, 60, 0.1);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: none;
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .footer-inside {
            width: 100%;
            margin-top: 35px;
            padding-top: 20px;
            text-align: center;
            border-top: 1px solid var(--container-border);
            font-size: 0.85rem;
            color: var(--text-color-muted);
        }

        .footer-inside a {
            color: var(--input-focus);
            text-decoration: none;
            font-weight: 600;
        }

        .footer-inside a:hover {
            text-decoration: underline;
        }

        .footer-inside p {
            margin: 5px 0;
        }
    </style>
</head>

<body>
    <div class="top-controls">
        <button class="top-btn" id="lang_toggle" aria-label="Zmień język">EN</button>
        <button class="top-btn" id="theme_toggle" aria-label="Zmień motyw">
            <svg viewBox="0 0 24 24" id="icon_moon" style="display:none;">
                <path
                    d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z" />
            </svg>
            <svg viewBox="0 0 24 24" id="icon_sun">
                <path
                    d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zM2 13h2c.55 0 1-.45 1-1s-.45-1-1-1H2c-.55 0-1 .45-1 1s.45 1 1 1zm18 0h2c.55 0 1-.45 1-1s-.45-1-1-1h-2c-.55 0-1 .45-1 1s.45 1 1 1zM11 2v2c0 .55.45 1 1 1s1-.45 1-1V2c0-.55-.45-1-1-1s-1 .45-1 1zm0 18v2c0 .55.45 1 1 1s1-.45 1-1v-2c0-.55-.45-1-1-1s-1 .45-1 1zM5.99 4.58c-.39-.39-1.03-.39-1.41 0-.39.39-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0 .39-.39.39-1.03 0-1.41L5.99 4.58zm12.37 12.37c-.39-.39-1.03-.39-1.41 0-.39.39-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0 .39-.39.39-1.03 0-1.41l-1.06-1.06zm1.06-10.96c.39-.39.39-1.03 0-1.41-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41.39.39 1.03.39 1.41 0l1.06-1.06zM7.05 18.36c.39-.39.39-1.03 0-1.41-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41.39.39 1.03.39 1.41 0l1.06-1.06z" />
            </svg>
        </button>
    </div>

    <div class="container">
        <div class="header">
            <h1>Bilingual Posting Tool</h1>
            <p data-i18n="subtitle">Narzędzie do tworzenia dwujęzycznych postów na media społecznościowe</p>
        </div>

        <div class="error-message" id="error_msg"></div>

        <div class="form-group">
            <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom: 8px;">
                <label for="api_key" data-i18n="lbl_api" style="margin-bottom:0;">Twój klucz DeepL (API)</label>
                <div class="tooltip-wrapper">
                    <svg class="info-icon" viewBox="0 0 24 24" width="20" height="20">
                        <path
                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z" />
                    </svg>
                    <div class="tooltip-box">
                        <span data-i18n="tt_api">Skąd wziąć klucz? Załóż bezpłatne konto DeepL API</span>
                        <a href="https://www.deepl.com/en/signup?cta=checkout&is_api=true" target="_blank"
                            rel="noopener noreferrer" data-i18n="tt_api_link">tutaj</a>.
                    </div>
                </div>
            </div>
            <input type="password" id="api_key" data-i18n-ph="ph_api"
                placeholder="Wklej klucz kończący się na :fx lub oryginalny płatnej wersji Pro" />
        </div>

        <div class="flex-group">
            <div class="form-group">
                <label data-i18n="lbl_src">Z języka</label>
                <div class="custom-select-trigger" id="src_dropdown_trigger" data-value="PL">🇵🇱 Polski</div>
                <div class="custom-options" id="src_dropdown_opts">
                    <div class="search-lang">
                        <input type="text" id="src_search" data-i18n-ph="ph_search" placeholder="Szukaj...">
                    </div>
                    <div class="options-list" id="src_list"></div>
                </div>
            </div>
            <div class="form-group">
                <label data-i18n="lbl_target">Na język</label>
                <div class="custom-select-trigger" id="target_dropdown_trigger" data-value="EN">🇬🇧 Angielski (English)
                </div>
                <div class="custom-options" id="target_dropdown_opts">
                    <div class="search-lang">
                        <input type="text" id="target_search" data-i18n-ph="ph_search" placeholder="Szukaj...">
                    </div>
                    <div class="options-list" id="target_list"></div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="input_text" data-i18n="lbl_text">Tekst oryginalny</label>
            <textarea id="input_text" data-i18n-ph="ph_text" placeholder="Główna, źródłowa treść wejściowa"></textarea>
        </div>

        <button class="action-btn" id="btn_translate">
            <span data-i18n="btn_trans">Prztłumacz logiką asynchroniczną i formatuj flagami</span>
            <div class="loader" id="loader"></div>
        </button>

        <div class="output-group" id="output_group">
            <label for="output_text" data-i18n="lbl_out">Gotowy kompozyt tekstu</label>
            <textarea id="output_text" readonly spellcheck="false"></textarea>
            <button class="action-btn btn-secondary" id="btn_copy" data-i18n="btn_copy">Skopiuj do schowka</button>
        </div>

        <div class="footer-inside">
            <p>© 2026 Tomasz Dunia.</p>
            <p><span data-i18n="f_cc">Treść dostępna na licencji</span> <a
                    href="https://creativecommons.org/licenses/by-sa/4.0/deed.pl" target="_blank"
                    rel="noopener noreferrer">CC BY-SA 4.0</a>.</p>
            <p><span data-i18n="f_mast">Znajdziemy się na powiązanym serwisie Fedivers, na</span> <a
                    href="https://infosec.exchange/@to3k" target="_blank" rel="noopener noreferrer">Mastodon</a>!</p>
            <p><span data-i18n="f_github">Kod tego narzędzia jest otwarty i dostępny na</span> <a
                    href="https://github.com/to3k/bilangpost" target="_blank" rel="noopener noreferrer">GitHub</a>.</p>
        </div>
    </div>

    <div class="cookie-banner" id="cookie_banner">
        <p data-i18n="cookie_msg">Używamy cookies m.in aby zrezygnować w kodzie z nadmiernego śledzenia do ułatwienia
            pracy, by pamiętać wybrany UI na zawsze!</p>
        <div class="cookie-buttons">
            <button class="cookie-btn cookie-btn-decline" id="btn_cookie_decline" data-i18n="cookie_decline">Brak wolnej
                zgody</button>
            <button class="cookie-btn" id="btn_cookie_accept" data-i18n="cookie_accept">Tak, zrozumiałem, chętnie
                zachowam.</button>
        </div>
    </div>

    <script>
        // SŁOWNIK JĘZYKÓW (Statyczna predefiniowana lista popularnych rynkowych języków DeepL ze flagami w celu przyspieszenia UX bez API wywołujących opóźnienia)
        const langData = {
            'PL': ['Polski', 'Polish', '🇵🇱'],
            'EN': ['Angielski', 'English', '🇬🇧'],
            'DE': ['Niemiecki', 'German', '🇩🇪'],
            'FR': ['Francuski', 'French', '🇫🇷'],
            'ES': ['Hiszpański', 'Spanish', '🇪🇸'],
            'IT': ['Włoski', 'Italian', '🇮🇹'],
            'PT': ['Portugalski', 'Portuguese', '🇵🇹'],
            'RU': ['Rosyjski', 'Russian', '🇷🇺'],
            'ZH': ['Chiński', 'Chinese', '🇨🇳'],
            'JA': ['Japoński', 'Japanese', '🇯🇵'],
            'CS': ['Czeski', 'Czech', '🇨🇿'],
            'DA': ['Duński', 'Danish', '🇩🇰'],
            'EL': ['Grecki', 'Greek', '🇬🇷'],
            'FI': ['Fiński', 'Finnish', '🇫🇮'],
            'HU': ['Węgierski', 'Hungarian', '🇭🇺'],
            'NL': ['Holenderski', 'Dutch', '🇳🇱'],
            'SK': ['Słowacki', 'Slovak', '🇸🇰'],
            'SV': ['Szwedzki', 'Swedish', '🇸🇪'],
            'TR': ['Turecki', 'Turkish', '🇹🇷'],
            'UK': ['Ukraiński', 'Ukrainian', '🇺🇦']
        };

        // SŁOWNIK INTERFEJSU UI - Wewnętrzna pętla do zmiany opisów między Angielskim i Polskim modelem tekstów.
        const i18n = {
            'pl': {
                'subtitle': 'Narzędzie do tworzenia dwujęzycznych postów na media społecznościowe',
                'lbl_api': 'Twój klucz API DeepL',
                'ph_api': 'Wklej klucz tutaj',
                'lbl_src': 'Język wejściowy',
                'lbl_target': 'Język docelowy',
                'ph_search': 'Wyszukaj...',
                'lbl_text': 'Treść posta do przetłumaczenia',
                'ph_text': 'Wpisz tekst do tłumaczenia...',
                'btn_trans': 'Tłumacz i stwórz post',
                'lbl_out': 'Gotowy post',
                'btn_copy': 'Skopiuj do schowka',
                'btn_copied': 'Skopiowano do schowka',
                'tt_api': 'Skąd wziąć klucz? Załóż bezpłatne konto DeepL API ',
                'tt_api_link': 'na tej stronie',
                'cookie_msg': 'Cześć! Ta strona używa ciasteczek (cookies) wyłącznie do dwóch celów: zapamiętania klucza API DeepL, aby nie było konieczności podawania go za każdym razem, i wybranego języka oraz motywu strony. Strona w żaden inny sposób nie śledzi użytkownika, a brak wyrażenia zgody na używanie ciasteczek całkowicie wyłącza tę funkcjonalność.',
                'cookie_accept': 'Wyrażam zgodę',
                'cookie_decline': 'Nie wyrażam zgody',
                'f_cc': 'Narzędzie dostępne na licencji',
                'f_mast': 'Znajdź mnie na',
                'f_github': 'Kod tego narzędzia jest otwarty i dostępny na',
                'err_empty_api': 'Nie podano klucza API.',
                'err_empty_txt': 'Brak tekstu do tłumaczenia.',
                'err_comm': 'Nieoczekiwany błąd, skontaktuj się z administratorem.'
            },
            'en': {
                'subtitle': 'Tool for creating bilingual posts for social media',
                'lbl_api': 'Your DeepL API Key',
                'ph_api': 'Insert the key here',
                'lbl_src': 'From',
                'lbl_target': 'To',
                'ph_search': 'Search...',
                'lbl_text': 'Base message',
                'ph_text': 'Message to be translated...',
                'btn_trans': 'Translate and create post',
                'lbl_out': 'Ready post',
                'btn_copy': 'Copy to clipboard',
                'btn_copied': 'Copied to clipboard',
                'tt_api': 'How to obtain the key? Create a free DeepL API account ',
                'tt_api_link': 'on this webpage',
                'cookie_msg': 'Hello there! This site uses cookies purely for functional comfort: memory of stored DeepL API key to limit continuous copy-pasting, preserving theme and language settings. Zero external analytical tracking invoked here. If refused we maintain zero footprint.',
                'cookie_accept': 'I agree',
                'cookie_decline': 'I refuse',
                'f_cc': 'Creation published operating under authority of',
                'f_mast': 'Find me on',
                'f_github': 'Code of this app is open and available on',
                'err_empty_api': 'No API key was provided',
                'err_empty_txt': 'Nothing to translate.',
                'err_comm': 'Unexpected error, please contact administrator.'
            }
        };

        // Zwykła i uniwersalna funkcja wydobywająca plik cookie z domeny wyciągająca ze zredukowanym wyrażeniem poszukiwaną stripturę tekstową
        function getCookie(name) {
            let m = document.cookie.match(new RegExp("(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"));
            return m ? decodeURIComponent(m[1]) : undefined;
        }

        // Osadzanie ciasteczka z ograniczeniami po opublikowaniu
        function setCookie(name, value, days) {
            let expires = "";
            if (days) {
                let date = new Date(); date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + encodeURIComponent(value) + expires + "; path=/; SameSite=Lax";
        }

        // Identyfikatory na stałe powiązane z HTML aby sterować elementami z użyciem mechaniki interfejsów JS DOM
        let uiLang = 'pl'; // Inicjalizacja domyślnego jezyka polskiego, później być może nadpisanego
        let currentTheme = 'light';
        let cookiesAccepted = getCookie('cookies_accepted');

        // Funkcja uaktualniająca układ napisów używajac tablic słowników wyżej zależnie od wyboru użyszkodnika na bazie ID z data-i18n
        function applyI18n() {
            document.querySelectorAll('[data-i18n]').forEach(el => {
                let key = el.getAttribute('data-i18n');
                if (i18n[uiLang][key]) el.innerText = i18n[uiLang][key];
            });
            document.querySelectorAll('[data-i18n-ph]').forEach(el => {
                let key = el.getAttribute('data-i18n-ph');
                if (i18n[uiLang][key]) el.setAttribute('placeholder', i18n[uiLang][key]);
            });
            document.getElementById('lang_toggle').innerText = uiLang === 'pl' ? 'EN' : 'PL';

            // Przerysowanie domyślnych etykiet języków wybranych, tak by zachowały formę językową strony (Polish vs Polski)
            ['src', 'target'].forEach(type => {
                const trigger = document.getElementById(`${type}_dropdown_trigger`);
                if (trigger) {
                    const code = trigger.getAttribute('data-value');
                    // Dynamiczne pobranie z tablicy w oparciu o aktualny `uiLang`
                    const name = uiLang === 'pl' ? langData[code][0] : langData[code][1];
                    const emoji = langData[code][2];
                    trigger.innerHTML = `${emoji} ${name}`;
                }
            });
        }

        // Tłumaczenie przycisku (dla obsługi w locie przełączania i wywołania na starcie zapisanego stanu ze startu)
        document.getElementById('lang_toggle').addEventListener('click', () => {
            uiLang = uiLang === 'pl' ? 'en' : 'pl';
            applyI18n();
            if (cookiesAccepted === '1') setCookie('ui_lang', uiLang, 365);
            // Odświeżenie wpisów dropdown gdy wywołane by dopasować imienny tekst z tablicy z j. angielskim i polskim
            renderOptions('src', document.getElementById('src_search').value);
            renderOptions('target', document.getElementById('target_search').value);
        });

        function resolveLangName(langCode) {
            return uiLang === 'pl' ? langData[langCode][0] : langData[langCode][1];
        }

        // Funkcja renderująca obiekty interaktywnego panelu Dropdown wyszukiwania w tablicy stałych flag
        function renderOptions(type, filter = '') {
            const listEl = document.getElementById(`${type}_list`);
            listEl.innerHTML = ''; // Oprażnianie uprzedniej formy
            const f = filter.toLowerCase();
            for (let code in langData) {
                let name = resolveLangName(code);
                let emoji = langData[code][2];
                if (name.toLowerCase().includes(f) || code.toLowerCase().includes(f)) {
                    let div = document.createElement('div');
                    div.className = 'option-item';
                    div.innerHTML = `<span>${emoji}</span> <span>${name} (${code})</span>`;
                    // Nadpisujemy stan ukrytego "Input" by zapamiętać wartość gdy wyklika na to 
                    div.addEventListener('click', () => {
                        const trigger = document.getElementById(`${type}_dropdown_trigger`);
                        trigger.setAttribute('data-value', code);
                        trigger.innerHTML = `${emoji} ${name}`;
                        document.getElementById(`${type}_dropdown_opts`).classList.remove('open');
                        trigger.classList.remove('active');
                    });
                    listEl.appendChild(div);
                }
            }
        }

        // Wdrożenie dla dwóch okien naraz pętli obsługi i odświeżania wpisywania w box'ach filtrujących. 
        ['src', 'target'].forEach(type => {
            const trigger = document.getElementById(`${type}_dropdown_trigger`);
            const opts = document.getElementById(`${type}_dropdown_opts`);
            const search = document.getElementById(`${type}_search`);
            renderOptions(type); // Start-up wyciągnięcia

            trigger.addEventListener('click', (e) => {
                e.stopPropagation(); // Blokuje wystrzelenie w pustkę
                const isOpen = opts.classList.contains('open');
                // Zamykamy inne w wypadku kliku po drugim polu
                document.querySelectorAll('.custom-options').forEach(o => o.classList.remove('open'));
                document.querySelectorAll('.custom-select-trigger').forEach(o => o.classList.remove('active'));

                if (!isOpen) {
                    opts.classList.add('open');
                    trigger.classList.add('active');
                    search.focus();
                }
            });

            search.addEventListener('input', (e) => renderOptions(type, e.target.value));
            search.addEventListener('click', (e) => e.stopPropagation());
        });

        document.addEventListener('click', () => {
            document.querySelectorAll('.custom-options').forEach(o => o.classList.remove('open'));
            document.querySelectorAll('.custom-select-trigger').forEach(o => o.classList.remove('active'));
        });

        // Pociągnięcie logicznej mechaniki Cookies dla baneru na start (Zabezpieczenie UI start-pointów)
        if (cookiesAccepted === '1') {
            const savedKey = getCookie('deepl_api_key');
            if (savedKey) document.getElementById('api_key').value = savedKey;

            const savedLang = getCookie('ui_lang');
            if (savedLang) uiLang = savedLang;
        } else if (cookiesAccepted === undefined) {
            // Wypłynięcie informacji o RODO z półsekundowym płynnym opóźnieniem
            setTimeout(() => document.getElementById('cookie_banner').classList.add('show'), 500);
        }

        applyI18n(); // Sprowadza po raz pierwszy aktualną tablice by na wczytaniu pokazało się ładnie jak powinno.

        // Realizacja Motywu (Light/Dark) używając manipulacji parametrem [data-theme] podłączonym wcześniej w CSS root. 
        function applyTheme(theme) {
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
                document.getElementById('icon_sun').style.display = 'none';
                document.getElementById('icon_moon').style.display = 'block';
            } else {
                document.documentElement.removeAttribute('data-theme');
                document.getElementById('icon_moon').style.display = 'none';
                document.getElementById('icon_sun').style.display = 'block';
            }
        }

        if (cookiesAccepted === '1' && getCookie('theme')) {
            currentTheme = getCookie('theme');
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            currentTheme = 'dark'; // Z czytania z wariantu sytemu Windows / Mac
        }
        applyTheme(currentTheme);

        document.getElementById('theme_toggle').addEventListener('click', () => {
            currentTheme = currentTheme === 'light' ? 'dark' : 'light';
            applyTheme(currentTheme);
            if (cookiesAccepted === '1') setCookie('theme', currentTheme, 365);
        });

        // Skrypt wykonujący obsługę dla nacisnięcia zgód zamykających modal flagujący.
        document.getElementById('btn_cookie_accept').addEventListener('click', () => {
            setCookie('cookies_accepted', '1', 365);
            setCookie('theme', currentTheme, 365);
            setCookie('ui_lang', uiLang, 365);
            const k = document.getElementById('api_key').value.trim();
            if (k) setCookie('deepl_api_key', k, 365);
            cookiesAccepted = '1';
            document.getElementById('cookie_banner').classList.remove('show');
        });

        document.getElementById('btn_cookie_decline').addEventListener('click', () => {
            setCookie('cookies_accepted', '0', 365);
            cookiesAccepted = '0';
            document.cookie = "deepl_api_key=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
            document.cookie = "theme=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
            document.cookie = "ui_lang=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
            document.getElementById('cookie_banner').classList.remove('show');
        });

        // Podsystem generujący opisy błędów po stronie skryptu
        const errorMsg = document.getElementById('error_msg');
        function showError(msgStr) { errorMsg.innerText = msgStr; errorMsg.style.display = 'block'; }

        // Asystująca logika zapytania uderzająca bez migania do naszego własnego systemu na górze nad kodem body.
        document.getElementById('btn_translate').addEventListener('click', async () => {
            errorMsg.style.display = 'none';
            document.getElementById('output_group').style.display = 'none';

            const apiKey = document.getElementById('api_key').value.trim();
            const text = document.getElementById('input_text').value.trim();
            // Wyciągnięcie ze sztucznego pola wyboru kodu (np. 'PL' do bazy zmiennej z atrybutu ukrytego div'a).
            const src = document.getElementById('src_dropdown_trigger').getAttribute('data-value');
            const target = document.getElementById('target_dropdown_trigger').getAttribute('data-value');

            // Przerywamy bezużyteczny zablokowany bez danych fetch i oddajemy odzewem przez UI.
            if (!apiKey) return showError(i18n[uiLang]['err_empty_api']);
            if (!text) return showError(i18n[uiLang]['err_empty_txt']);

            if (cookiesAccepted === '1') setCookie('deepl_api_key', apiKey, 365);

            const btnT = document.getElementById('btn_translate');
            const loader = document.getElementById('loader');
            btnT.disabled = true; loader.style.display = 'block';

            // Konstrukcja pętli próbującej pobrać z JSON przez POST bez ponownego wymuszenia F5 do backendu
            try {
                // Post do lokalnego pliku indeksu z wprowadzaniem zdeklarowanego JSON 
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ api_key: apiKey, text: text, src_lang: src, target_lang: target })
                });

                const data = await response.json(); // Dekodowanie nadeszłego

                if (!response.ok || data.error) {
                    showError(data.error || "Wystąpił nieznany błąd podczas tłumaczenia."); // Wyjątek podany z DeepL przez proxy PHP 
                } else if (data.translation) {
                    // Flagi
                    const finalMsg = `${langData[src][2]} ${text}\n- - - - -\n${langData[target][2]} ${data.translation}`;
                    const outText = document.getElementById('output_text');
                    outText.value = finalMsg;
                    document.getElementById('output_group').style.display = 'block';

                    // Funkcjonalność dostosowana tak, by wysokość po udanym przetłumaczeniu zeszła kaskadowo w dół, likwidując schowany boczny scroller
                    outText.style.height = 'auto';
                    outText.style.height = (outText.scrollHeight + 5) + 'px';
                }
            } catch (err) {
                showError(i18n[uiLang]['err_comm']);
                console.error(err);
            } finally {
                btnT.disabled = false; loader.style.display = 'none';
            }
        });

        // Kopiowanie do schowka
        document.getElementById('btn_copy').addEventListener('click', () => {
            const outText = document.getElementById('output_text');
            const btnCopy = document.getElementById('btn_copy');
            outText.select();
            outText.setSelectionRange(0, 99999);
            try {
                navigator.clipboard.writeText(outText.value).then(() => {
                    const orig = btnCopy.innerText;
                    btnCopy.innerText = i18n[uiLang]['btn_copied'];
                    setTimeout(() => btnCopy.innerText = orig, 2000);
                });
            } catch (e) {
                document.execCommand('copy');
                const orig = btnCopy.innerText;
                btnCopy.innerText = i18n[uiLang]['btn_copied'];
                setTimeout(() => btnCopy.innerText = orig, 2000);
            }
        });
    </script>
</body>

</html>
