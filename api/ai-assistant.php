<?php
/**
 * Al-Falah Marketing — Hybrid Agent AI Assistant
 *
 * Priority 1: Explicit site intents → local knowledge (instant)
 * Priority 2: OpenAI agent (general knowledge, web-style Q&A, market/tech)
 * Priority 3: DuckDuckGo + Wikipedia if no API key / OpenAI failure
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];
$message = trim((string)($data['message'] ?? ''));
$lang = strtolower(substr((string)($data['lang'] ?? 'en'), 0, 2));
if ($lang !== 'fr') $lang = 'en';
$history = is_array($data['history'] ?? null) ? array_slice($data['history'], -6) : [];

if ($message === '' || mb_strlen($message) > 800) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid message']);
    exit;
}

// Rate limit ~30/min per IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
$rlFile = sys_get_temp_dir() . '/alfalah_ai_' . md5($ip);
$now = time();
$hits = [];
if (is_file($rlFile)) {
    $hits = array_filter(array_map('intval', explode(',', (string)@file_get_contents($rlFile))), fn($t) => $t > $now - 60);
}
if (count($hits) >= 30) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests']);
    exit;
}
$hits[] = $now;
@file_put_contents($rlFile, implode(',', $hits));

$WA = 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . rawurlencode(
    $lang === 'fr'
        ? 'Bonjour Al-Falah, je souhaite une consultation confidentielle.'
        : 'Hello Al-Falah, I would like a confidential consultation.'
);

// ----- 1) Strict local intents only -----
$local = local_match_strict($message, $lang, $WA);
if ($local !== null) {
    echo json_encode([
        'reply' => $local,
        'source' => 'local',
        'status' => 'ok',
        'label' => $lang === 'fr' ? 'Base de connaissances Al-Falah' : 'Al-Falah knowledge base',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ----- 2) OpenAI agent (primary for everything else) -----
$apiKey = defined('OPENAI_API_KEY') ? trim((string)OPENAI_API_KEY) : '';
if ($apiKey === '' || $apiKey === 'PASTE_YOUR_OPENAI_API_KEY_HERE') {
    $apiKey = trim((string)(getenv('OPENAI_API_KEY') ?: ''));
}

if ($apiKey !== '' && $apiKey !== 'PASTE_YOUR_OPENAI_API_KEY_HERE') {
    // Optional: pull a short web snippet to ground the agent
    $webCtx = web_search_snippet($message);
    $agent = openai_agent($apiKey, $message, $lang, $WA, $history, $webCtx);
    if ($agent !== null) {
        echo json_encode([
            'reply' => $agent,
            'source' => $webCtx ? 'hybrid' : 'agent',
            'status' => 'ok',
            'label' => $lang === 'fr'
                ? ($webCtx ? 'Agent IA · sources web' : 'Agent IA Al-Falah')
                : ($webCtx ? 'AI agent · web sources' : 'Al-Falah AI agent'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ----- 3) Web search only (no / failed OpenAI) -----
$web = web_search_answer($message, $lang, $WA);
if ($web !== null) {
    echo json_encode([
        'reply' => $web,
        'source' => 'web',
        'status' => 'ok',
        'label' => $lang === 'fr' ? 'Recherche web en direct' : 'Live web search',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ----- 4) Fallback -----
$needKey = ($apiKey === '' || $apiKey === 'PASTE_YOUR_OPENAI_API_KEY_HERE');
$fallback = $lang === 'fr'
    ? ($needKey
        ? 'Pour les questions hors site, configurez votre clé OpenAI dans <code>api/config.php</code> (OPENAI_API_KEY). En attendant : <a href="services.html">Services</a> · <a href="institutions.html">Institutions</a> · <a href="contact.html">Contact</a> · <a href="' . $WA . '" target="_blank" rel="noopener">WhatsApp</a>.'
        : 'Je n’ai pas pu obtenir de réponse agent pour le moment. Réessayez, ou <a href="contact.html">contactez l’équipe</a> · <a href="' . $WA . '" target="_blank" rel="noopener">WhatsApp</a>.')
    : ($needKey
        ? 'For off-site questions, add your OpenAI key in <code>api/config.php</code> (OPENAI_API_KEY). Meanwhile: <a href="services.html">Services</a> · <a href="institutions.html">Institutions</a> · <a href="contact.html">Contact</a> · <a href="' . $WA . '" target="_blank" rel="noopener">WhatsApp</a>.'
        : 'The agent could not complete a reply right now. Please try again, or <a href="contact.html">contact the team</a> · <a href="' . $WA . '" target="_blank" rel="noopener">WhatsApp</a>.');

echo json_encode([
    'reply' => $fallback,
    'source' => 'local',
    'status' => 'ok',
    'label' => $lang === 'fr' ? 'Configuration requise' : 'Setup required',
], JSON_UNESCAPED_UNICODE);

// ================= helpers =================

function local_match_strict(string $text, string $lang, string $WA): ?string {
    $t = mb_strtolower($text);

    // Only match when clearly about Al-Falah / this website
    $aboutSite = preg_match(
        '/\b(al-?falah|alfalah|your (agency|company|team|services|website|site)|vos? (services|tarifs|coordonnées)|notre (agence|site)|this (website|site)|on your site)\b/u',
        $t
    );

    $replies = site_replies($lang, $WA);

    // Explicit product intents (short queries or with site context)
    if (preg_match('/\b(institutional platforms?|plateformes? institutionnelles?|portails? officiels?)\b/u', $t)) {
        return $replies['platform'];
    }
    if (preg_match('/\b(public image|image publique|branding institutionnel|institutional branding)\b/u', $t)) {
        return $replies['branding'];
    }
    if (preg_match('/\b(ai (and|&) data for governance|ia .* gouvernance|discretion (and|&) protocol|discrétion.*protocole)\b/u', $t)) {
        if (str_contains($t, 'protocol') || str_contains($t, 'protocole') || str_contains($t, 'discret')) {
            return $replies['protocol'];
        }
        return $replies['ai'];
    }
    if (preg_match('/\b(book (a )?(call|consultation)|réserver.*(appel|consultation)|strategy call|free consultation)\b/u', $t)) {
        return $replies['consult'];
    }
    if (preg_match('/\b(whatsapp|how (can|do) i (reach|contact) you|vos? (coordonnées|contacts?)|phone number|numéro)\b/u', $t)) {
        return $replies['whatsapp'];
    }
    if (preg_match('/\b(your pricing|vos? tarifs|how much (do you|does it) cost|devis al-?falah)\b/u', $t)) {
        return $replies['pricing'];
    }
    if (preg_match('/\b(your portfolio|vos? réalisations|case stud(y|ies) on (the )?site)\b/u', $t)) {
        return $replies['portfolio'];
    }
    if (preg_match('/\b(about al-?falah|à propos d[\'’]?al-?falah|who (is|are) (the )?founder|abdulrahman jalloh)\b/u', $t)) {
        return $replies['about'];
    }
    if (preg_match('/\b(your services|vos services|what (do you|services do you) offer)\b/u', $t)) {
        return $replies['services'];
    }
    if ($aboutSite && preg_match('/\b(contact|email|address|adresse)\b/u', $t)) {
        return $replies['contact'];
    }

    return null; // → agent / web
}

function site_replies(string $lang, string $WA): array {
    if ($lang === 'fr') {
        return [
            'platform' => 'Nous concevons des sites et portails officiels sécurisés pour ministères, agences et ONG — mobile-first, multilingues et respectueux du protocole. <a href="institutions-platforms.html">Voir les plateformes →</a>',
            'branding' => 'Image publique & branding : identité visuelle institutionnelle, présence de décideurs et communication protocolaire. <a href="institutions-branding.html">En savoir plus →</a>',
            'ai' => 'IA & data pour la gouvernance : tableaux de bord, reporting interne et automatisations concrètes, confidentialité d’abord. <a href="institutions-ai.html">Explorer →</a>',
            'protocol' => 'Discrétion & protocole : contact dédié, validation par jalons et traitement confidentiel. <a href="institutions-protocol.html">Notre méthode →</a>',
            'consult' => 'Demandez un briefing confidentiel via le formulaire ou WhatsApp. <a href="contact.html">Formulaire →</a> · <a href="' . $WA . '" target="_blank" rel="noopener">WhatsApp →</a>',
            'whatsapp' => 'WhatsApp : Guinée +224 611 351 302 · Sierra Leone +232 74 321 916 · USA +1 240-280-6137. <a href="' . $WA . '" target="_blank" rel="noopener">Ouvrir WhatsApp →</a>',
            'services' => 'Services : développement web, marketing digital, IA & automatisation, branding, contenu et solutions IT — plus une offre institutionnelle. <a href="services.html">Services →</a> · <a href="institutions.html">Institutions →</a>',
            'about' => 'Al-Falah Marketing est une agence bilingue (FR/EN) aux racines guinéennes, active en Guinée, Sierra Leone et aux USA (Laurel, MD). Fondateur : Abdulrahman Jalloh. <a href="about.html">À propos →</a>',
            'portfolio' => 'Consultez nos réalisations clients. <a href="portfolio.html">Portfolio →</a>',
            'contact' => 'Email info@al-falahmarketing.com · WhatsApp +224 611 351 302 · USA +1 240-280-6137 · Coyah (Guinée) et 8468 Winding Trail, Laurel, MD 20724. <a href="contact.html">Contact →</a>',
            'pricing' => 'Pas de grilles publiques : les périmètres sont définis après un appel stratégie gratuit. <a href="contact.html">Réserver un appel →</a>',
        ];
    }
    return [
        'platform' => 'We build secure official websites and portals for ministries, agencies, and NGOs — mobile-first, multilingual, and protocol-aware. <a href="institutions-platforms.html">View platforms →</a>',
        'branding' => 'Public image & branding covers institutional visual identity, senior-official presence, and protocol-aligned communication. <a href="institutions-branding.html">Learn more →</a>',
        'ai' => 'AI & data for governance includes project dashboards, internal reporting, and practical automation with confidentiality first. <a href="institutions-ai.html">Explore AI services →</a>',
        'protocol' => 'Discretion & protocol means dedicated contacts, milestone validation, and confidential handling of sensitive materials. <a href="institutions-protocol.html">How we work →</a>',
        'consult' => 'Request a confidential briefing on our contact page or WhatsApp. <a href="contact.html">Contact form →</a> · <a href="' . $WA . '" target="_blank" rel="noopener">WhatsApp →</a>',
        'whatsapp' => 'WhatsApp: Guinea +224 611 351 302 · Sierra Leone +232 74 321 916 · USA +1 240-280-6137. <a href="' . $WA . '" target="_blank" rel="noopener">Open WhatsApp →</a>',
        'services' => 'Core services: Web Development, Digital Marketing, AI & Automation, Branding, Content, and IT Solutions — plus a dedicated institutional track. <a href="services.html">Services →</a> · <a href="institutions.html">Institutions →</a>',
        'about' => 'Al-Falah Marketing is a bilingual (EN/FR) agency with Guinea roots, serving Guinea, Sierra Leone, and the USA (Laurel, MD). Founder: Abdulrahman Jalloh. <a href="about.html">About us →</a>',
        'portfolio' => 'See client work on our portfolio page. <a href="portfolio.html">Portfolio →</a>',
        'contact' => 'Email info@al-falahmarketing.com · WhatsApp +224 611 351 302 · USA +1 240-280-6137 · Coyah, Guinea and 8468 Winding Trail, Laurel, MD 20724. <a href="contact.html">Contact →</a>',
        'pricing' => 'We do not publish fixed public pricing; scopes are defined after a free strategy call. <a href="contact.html">Book a call →</a>',
    ];
}

function openai_agent(string $apiKey, string $query, string $lang, string $WA, array $history, ?string $webCtx): ?string {
    $endpoint = defined('OPENAI_BASE_URL') ? OPENAI_BASE_URL : 'https://api.openai.com/v1/chat/completions';
    $model = defined('OPENAI_MODEL') ? OPENAI_MODEL : 'gpt-4o-mini';

    $siteContext = <<<CTX
You are the on-site AI agent for Al-Falah Marketing (https://al-falahmarketing.com), a bilingual (English/French) digital marketing and IT agency serving SMBs and public institutions across Guinea, Sierra Leone, and the USA (Laurel, MD).

Agency facts:
- Founder & CEO: Abdulrahman Jalloh
- Services: Web Development, Digital Marketing (SEO/SEM/Social incl. TikTok), AI & Automation, Branding, Content Creation, IT Solutions
- Institutional pillars: Institutional platforms, Public image & branding, AI & data for governance, Discretion & protocol
- Contact: info@al-falahmarketing.com | WhatsApp +224 611 351 302 | SL +232 74 321 916 | USA +1 240-280-6137
- No public fixed pricing; free strategy consultation via contact form or WhatsApp: {$WA}

Behavior:
1) For questions about Al-Falah services, contact, portfolio, or process — answer with those facts and suggest relevant page links (plain paths like services.html, contact.html, institutions.html).
2) For general knowledge, technical, market-research, or current-events style questions — answer accurately using your knowledge and any WEB CONTEXT provided. Be factual; if uncertain, say so.
3) Tone: professional, secure, institutional/SMB-friendly. Concise (prefer under 180 words).
4) Language: reply in {$lang} (en or fr) matching the user.
5) Output HTML-safe plain text with optional simple <a href="..."> links. No markdown code fences. No invented Al-Falah case studies or fake metrics.
CTX;

    $messages = [
        ['role' => 'system', 'content' => $siteContext],
    ];
    foreach ($history as $h) {
        $role = ($h['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
        $content = trim((string)($h['content'] ?? ''));
        if ($content !== '') {
            $messages[] = ['role' => $role, 'content' => mb_substr(strip_tags($content), 0, 1000)];
        }
    }
    $userContent = $query;
    if ($webCtx) {
        $userContent .= "\n\nWEB CONTEXT (may be partial; verify against your knowledge):\n" . mb_substr($webCtx, 0, 2000);
    }
    $messages[] = ['role' => 'user', 'content' => $userContent];

    $payload = json_encode([
        'model' => $model,
        'messages' => $messages,
        'max_tokens' => 550,
        'temperature' => 0.4,
    ], JSON_UNESCAPED_UNICODE);

    if (!function_exists('curl_init')) return null;

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 45,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false || $code < 200 || $code >= 300) {
        error_log("AlFalah AI OpenAI error HTTP $code $err body=" . substr((string)$body, 0, 300));
        return null;
    }
    $j = json_decode($body, true);
    $text = trim((string)($j['choices'][0]['message']['content'] ?? ''));
    if ($text === '') return null;

    // Allow basic anchors the model may emit; escape the rest lightly
    $text = preg_replace('#<\s*script[^>]*>.*?<\s*/\s*script\s*>#is', '', $text);
    $text = strip_tags($text, '<a><br><b><strong><em><i><ul><ol><li><p>');
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    return nl2br($text, false);
}

function web_search_snippet(string $query): ?string {
    $url = 'https://api.duckduckgo.com/?q=' . rawurlencode($query) . '&format=json&no_html=1&skip_disambig=1';
    $json = http_get($url, 6);
    if (!$json) return null;
    $j = json_decode($json, true);
    if (!is_array($j)) return null;
    $parts = [];
    if (!empty($j['AbstractText'])) $parts[] = $j['AbstractText'];
    if (!empty($j['Answer'])) $parts[] = $j['Answer'];
    foreach (($j['RelatedTopics'] ?? []) as $rt) {
        if (!empty($rt['Text'])) $parts[] = $rt['Text'];
        if (count($parts) >= 4) break;
    }
    $s = trim(implode("\n", $parts));
    return $s !== '' ? $s : null;
}

function web_search_answer(string $query, string $lang, string $WA): ?string {
    $json = http_get('https://api.duckduckgo.com/?q=' . rawurlencode($query) . '&format=json&no_html=1&skip_disambig=1', 8);
    if ($json) {
        $j = json_decode($json, true);
        if (is_array($j)) {
            $abstract = trim((string)($j['AbstractText'] ?? ''));
            $heading = trim((string)($j['Heading'] ?? ''));
            $source = trim((string)($j['AbstractURL'] ?? ''));
            if ($abstract !== '') {
                $out = ($heading !== '' ? '<strong>' . htmlspecialchars($heading) . '</strong><br>' : '')
                    . htmlspecialchars($abstract);
                if ($source !== '') {
                    $out .= '<br><a href="' . htmlspecialchars($source) . '" target="_blank" rel="noopener noreferrer">Source</a>';
                }
                $out .= $lang === 'fr'
                    ? '<br><br><em>Pour les services Al-Falah : <a href="contact.html">contact</a> · <a href="' . $WA . '" target="_blank" rel="noopener">WhatsApp</a>.</em>'
                    : '<br><br><em>For Al-Falah services: <a href="contact.html">contact</a> · <a href="' . $WA . '" target="_blank" rel="noopener">WhatsApp</a>.</em>';
                return $out;
            }
            if (!empty($j['Answer'])) {
                return htmlspecialchars($j['Answer']);
            }
        }
    }
    $wikiLang = $lang === 'fr' ? 'fr' : 'en';
    $wjson = http_get("https://{$wikiLang}.wikipedia.org/w/api.php?action=opensearch&search=" . rawurlencode($query) . "&limit=1&namespace=0&format=json", 6);
    if ($wjson) {
        $w = json_decode($wjson, true);
        if (is_array($w) && !empty($w[1][0]) && !empty($w[2][0])) {
            $out = '<strong>' . htmlspecialchars($w[1][0]) . '</strong><br>' . htmlspecialchars($w[2][0]);
            if (!empty($w[3][0])) {
                $out .= '<br><a href="' . htmlspecialchars($w[3][0]) . '" target="_blank" rel="noopener noreferrer">Wikipedia</a>';
            }
            return $out;
        }
    }
    return null;
}

function http_get(string $url, int $timeout = 8): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'AlFalahAssistant/2.0 (+https://al-falahmarketing.com)',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body !== false && $code >= 200 && $code < 300) return $body;
        return null;
    }
    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'header' => "User-Agent: AlFalahAssistant/2.0\r\n"]]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : $body;
}
