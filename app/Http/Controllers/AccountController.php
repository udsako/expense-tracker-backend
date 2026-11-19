<?php

namespace App\Http\Controllers;

use App\Models\Account;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Smalot\PdfParser\Parser;

class AccountController extends Controller
{
    private const DATE_PATTERN = '\d{1,2}-[A-Za-z]{3}-\d{4}|\d{1,2}\s+[A-Za-z]{3}\s+\d{4}|\d{1,2}[-/]\d{1,2}[-/]\d{2,4}';

    private const AMOUNT_PATTERN = '-?\d{1,3}(?:,\d{3})*\.\d{2}';

    private const KEYWORDS = [
        'Food' => [
            'mega chicken', 'coldstone', 'cold stone', 'panarottis', 'chicken republic',
            'kilimanjaro', 'sweet sensation', 'tastee', 'the place', 'domino', 'pizza',
            'shawarma', 'suya', 'buka', 'restaurant', 'eatery', 'bakery', 'cafe', 'coffee',
            'yam', 'beef', 'chicken', 'potato', 'potatoes', 'rice', 'beans', 'plantain',
            'garri', 'fish', 'turkey', 'egg', 'eggs', 'bread', 'milk', 'palm oil', 'food',
            'groceries', 'dinner', 'lunch', 'breakfast',
        ],
        'Shopping' => [
            'jumia', 'temu', 'shein', 'aliexpress', 'konga', 'amazon', 'ebay', 'zara', 'asos', 'shoprite', 'spar', 'prince ebeano', 'blenco', 'justrite', 'supermarket',
        ],
        'Fashion & Beauty' => [
            'shoe', 'shoes', 'sneaker', 'sneakers', 'heels', 'sandals', 'slippers', 'bag', 'handbag',
            'clothes', 'clothing', 'dress', 'shirt', 'trouser', 'trousers', 'jeans', 'skirt', 'outfit',
            'boutique', 'fashion', 'tailor', 'fabric', 'asoebi',
            'salon', 'saloon', 'barber', 'barbing', 'hair', 'braids', 'wig', 'spa',
            'nails', 'pedicure', 'manicure', 'skincare', 'makeup', 'cosmetics', 'perfume', 'lotion',
        ],
        'Gadgets' => [
            'headphone', 'headphones', 'earpod', 'earpods', 'airpod', 'airpods', 'earpiece', 'headset',
            'charger', 'powerbank', 'power bank', 'phone', 'iphone', 'samsung', 'redmi', 'tecno', 'infinix',
            'laptop', 'macbook', 'tablet', 'ipad', 'gadget', 'gadgets', 'speaker', 'smartwatch', 'cable', 'adapter',
        ],
        'Self-Transfer' => [
            'self transfer', 'slef transfer', 'transfer to self', 'from self', 'to self',
            'self-transfer', 'own account', 'my account', 'internal transfer', 'self',
        ],
        'Family & Friends' => [
            'family', 'friend', 'friends', 'gift', 'gifts', 'mom', 'mum', 'mother', 'dad', 'father',
            'brother', 'sister', 'bro', 'sis', 'wife', 'husband', 'cousin', 'aunt', 'uncle',
            'birthday', 'allowance', 'upkeep', 'support', 'for mom', 'for mum', 'for dad',
        ],
        'Household Items' => [
            'tissue', 'toiletries', 'soap', 'detergent', 'bleach', 'hypo', 'omo', 'ariel',
            'sponge', 'broom', 'mop', 'bucket', 'bulb', 'battery', 'cooking gas', 'cylinder',
            'household', 'cleaning', 'disinfectant', 'insecticide',
        ],
        'Utilities' => [
            'ikedc', 'ekedc', 'aedc', 'ibedc', 'eedc', 'phed', 'kedco', 'jedc', 'yedc',
            'electricity', 'prepaid', 'meter token', 'water bill', 'waste', 'lawma',
            'dstv', 'gotv', 'startimes', 'showmax',
            'airtime', 'mobile data', 'mobtopup', 'vtu', 'mtn', 'airtel', 'glo', '9mobile',
            'spectranet', 'starlink',
        ],
        'Subscriptions' => [
            'applecom', 'apple.com', 'itunes', 'openai', 'chatgpt', 'netflix', 'spotify',
            'youtube', 'icloud', 'google storage', 'subscr', 'subscription', 'canva',
        ],
        'Transport' => [
            'bolt', 'uber', 'indrive', 'rida', 'lagride', 'fuel', 'petrol', 'diesel',
            'totalenergies', 'mobil', 'oando', 'nnpc', 'conoil', 'flight', 'arik', 'air peace',
        ],
        'Bank Charges' => [
            'stamp duty', 'smscharges', 'sms charges', 'sms charge', 'maintenance fee', 'card maintenance',
            'vat on', 'electronic money transfer levy', 'withholding tax',
        ],
    ];

    private const BANK_SIGNATURES = [
        'uba' => ['ubagroup', 'united bank for africa', 'uba plc', 'chatwithleo'],
        'polaris bank' => ['polarisbank', 'polaris bank', 'vulte', 'insav6'],
        'gtbank' => ['gtbank', 'guaranty trust', 'gtco', 'gtworld'],
        'access bank' => ['accessbank', 'access bank', 'accessmore'],
        'zenith bank' => ['zenithbank', 'zenith bank', 'eazybanking', 'zenithdirect'],
        'firstbank' => ['firstbank', 'first bank of nigeria', 'firstmobile'],
        'fidelity bank' => ['fidelitybank', 'fidelity bank'],
        'union bank' => ['unionbank', 'union bank of nigeria'],
        'stanbic ibtc' => ['stanbicibtc', 'stanbic ibtc'],
        'sterling bank' => ['sterlingbank', 'sterling bank', 'onebank'],
        'wema bank' => ['wemabank', 'wema bank', 'alat'],
        'fcmb' => ['fcmb', 'first city monument'],
        'ecobank' => ['ecobank'],
        'standard chartered' => ['standardchartered', 'standard chartered', 'sc.com'],
        'opay' => ['opay', 'opay digital services', 'owealth'],
        'kuda' => ['kuda', 'kuda microfinance', 'kudabank'],
        'palmpay' => ['palmpay'],
        'moniepoint' => ['moniepoint'],
        'fairmoney mfb' => ['fairmoney'],
        'vfd mfb' => ['vfd', 'vbank'],
        'lapo mfb' => ['lapo'],
        'carbon' => ['carbon', 'one finance'],
    ];

    public function index(Request $request)
    {
        $accounts = $request->user()->accounts()->orderBy('id')->get();

        return response()->json([
            'accounts' => $accounts,
            'total_balance' => (float) $accounts->sum('balance'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'institution' => 'required|string|max:100',
            'type' => 'required|in:commercial_bank,mfb,fintech',
        ]);

        if (! $this->namesMatch($request->user()->name, $validated['name'])) {
            return response()->json([
                'message' => "Security Check Failed: Account holder name must match your registered full name ({$request->user()->name}).",
            ], 422);
        }

        $account = $request->user()->accounts()->create([
            'name' => $validated['name'],
            'institution' => $validated['institution'],
            'type' => $validated['type'],
            'balance' => 0,
        ]);

        return response()->json([
            'message' => "{$account->name} - {$account->institution} added! Upload your bank statement PDF or CSV to populate your transactions.",
            'account' => $account,
        ], 201);
    }

    public function show(Request $request, Account $account)
    {
        if ($account->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $month = $request->query('month', now()->format('Y-m'));
        [$year, $monthNum] = explode('-', $month);

        $transactions = $account->expenses()
            ->whereYear('date', $year)
            ->whereMonth('date', $monthNum)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $incomesOnly = $transactions->where('type', 'income')->values();
        $expensesOnly = $transactions->where('type', 'expense')->values();

        $monthlyIncome = $incomesOnly->sum('amount');
        $monthlyExpense = $expensesOnly->sum('amount');
        $unclarifiedCount = $account->expenses()->where('is_excluded', true)->count();

        $breakdown = $expensesOnly
            ->groupBy(function ($tx) {
                if ($tx->is_excluded || strtolower($tx->category) === 'uncategorized') {
                    return 'Undocumented';
                }

                return ucfirst(trim($tx->category));
            })
            ->map(function ($items, $categoryName) {
                return [
                    'category' => $categoryName,
                    'total' => (float) $items->sum('amount'),
                    'count' => $items->count(),
                    'transactions' => $items->values()->map(fn ($t) => [
                        'id' => $t->id,
                        'date' => $t->date,
                        'title' => $t->title,
                        'amount' => (float) $t->amount,
                        'is_excluded' => $t->is_excluded,
                    ]),
                ];
            })
            ->sortByDesc('total')
            ->values();

        return response()->json([
            'account' => $account,
            'transactions' => $transactions,
            'income_overview' => $incomesOnly->map(fn ($t) => [
                'id' => $t->id,
                'date' => $t->date,
                'title' => $t->title,
                'category' => $t->category,
                'amount' => (float) $t->amount,
            ]),
            'category_breakdown' => $breakdown,
            'summary' => [
                'total_income' => (float) $monthlyIncome,
                'total_expense' => (float) $monthlyExpense,
                'current_balance' => (float) $account->balance,
                'unclarified_count' => $unclarifiedCount,
            ],
        ]);
    }

    public function previewStatement(Request $request, Account $account)
    {
        if ($account->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'statement' => 'required|file|mimes:csv,txt,pdf|max:15360',
        ]);

        $file = $request->file('statement');
        $extension = strtolower($file->getClientOriginalExtension());
        $referenceName = $account->name ?: $request->user()->name;

        if ($extension === 'pdf') {
            try {
                $parser = new Parser();
                $pdf = $parser->parseFile($file->getRealPath());
                $text = $pdf->getText();
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Could not read PDF. If it is password-protected, please unlock it first and try again.',
                ], 422);
            }

            // 1. If the PDF has no selectable text layer (scanned/image-only PDF), fall back to Tesseract OCR
            if (trim(preg_replace('/\s+/', '', $text)) === '') {
                $text = $this->extractTextWithOcr($file->getRealPath());

                if (trim(preg_replace('/\s+/', '', $text)) === '') {
                    return response()->json([
                        'message' => 'This PDF is an image-only document and OCR could not read it. Ensure Tesseract OCR and Poppler are installed, or upload a CSV statement.',
                    ], 422);
                }
            }

            // 2. Verify statement belongs to the logged-in user's registered profile name
            if (! $this->verifyStatementOwnership($text, $request->user()->name)) {
                return response()->json([
                    'message' => "Security Check Failed: The account holder name on this bank statement does not match your registered profile name ({$request->user()->name}). Uploading third-party statements is not allowed.",
                ], 422);
            }

            // 3. Verify statement belongs to the selected bank/institution
            if (! $this->verifyBankInstitution($text, $account->institution)) {
                return response()->json([
                    'message' => "Wrong Bank Statement: The uploaded document does not match {$account->institution}. Please upload your official {$account->institution} statement.",
                ], 422);
            }

            $parsed = $this->parseUniversalBankPdf($text, $referenceName);
        } else {
            $rawCsv = file_get_contents($file->getRealPath()) ?: '';
            $headerPreamble = $this->extractCsvPreamble($rawCsv);

            if ($headerPreamble !== '') {
                if (preg_match('/(account\s*name|customer\s*name|holder)/i', $headerPreamble) && ! $this->verifyStatementOwnership($headerPreamble, $request->user()->name)) {
                    return response()->json([
                        'message' => "Security Check Failed: The account holder name in this CSV does not match your registered profile name ({$request->user()->name}).",
                    ], 422);
                }

                if ($this->containsAnyKnownBankSignature($headerPreamble) && ! $this->verifyBankInstitution($headerPreamble, $account->institution)) {
                    return response()->json([
                        'message' => "Wrong Bank Statement: The uploaded CSV does not match {$account->institution}.",
                    ], 422);
                }
            }

            $parsed = $this->parseCsvFile($file->getRealPath(), $referenceName);
        }

        if (empty($parsed['rows'])) {
            return response()->json([
                'message' => 'No valid transactions could be detected in this file. Please ensure it is a digital bank statement PDF or CSV.',
            ], 422);
        }

        return response()->json([
            'rows' => $parsed['rows'],
            'opening_balance' => $parsed['opening_balance'],
            'closing_balance' => $parsed['closing_balance'],
            'statement_total_debit' => $parsed['statement_total_debit'] ?? null,
            'statement_total_credit' => $parsed['statement_total_credit'] ?? null,
        ]);
    }

        private function extractTextWithOcr(string $pdfPath): string
    {
        $tempDir = storage_path('app/ocr_' . uniqid());
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $ocrText = '';

        try {
            // 1. Render PDF pages to high-DPI PNG images using Poppler's pdftoppm
            $imagePrefix = $tempDir . DIRECTORY_SEPARATOR . 'page';
            $pdfCmd = sprintf('pdftoppm -png -r 250 %s %s 2>&1', escapeshellarg($pdfPath), escapeshellarg($imagePrefix));
            exec($pdfCmd);

            // 2. Locate Tesseract executable even if Windows PATH was not refreshed
            $tesseractBin = file_exists('C:\\Program Files\\Tesseract-OCR\\tesseract.exe')
                ? '"C:\\Program Files\\Tesseract-OCR\\tesseract.exe"'
                : 'tesseract';

            // 3. Run Tesseract OCR on each rendered page image
            $pages = glob($tempDir . DIRECTORY_SEPARATOR . 'page-*.png') ?: [];
            sort($pages);

            foreach ($pages as $pageImg) {
                $txtBase = $pageImg . '_out';
                $ocrCmd = sprintf('%s %s %s --psm 6 2>&1', $tesseractBin, escapeshellarg($pageImg), escapeshellarg($txtBase));
                exec($ocrCmd);

                if (file_exists($txtBase . '.txt')) {
                    $ocrText .= "\n" . file_get_contents($txtBase . '.txt');
                }
            }
        } finally {
            // 4. Immediately delete all temporary page images & text files for security
            foreach (glob($tempDir . DIRECTORY_SEPARATOR . '*') ?: [] as $tmpFile) {
                @unlink($tmpFile);
            }
            @rmdir($tempDir);
        }

        return $ocrText;
    }


    private function extractCsvPreamble(string $rawCsv): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $rawCsv);
        $preamble = [];

        foreach ($lines as $line) {
            $lower = strtolower($line);
            if (str_contains($lower, 'date') && (str_contains($lower, 'debit') || str_contains($lower, 'credit') || str_contains($lower, 'amount'))) {
                break;
            }
            if (trim($line) !== '') {
                $preamble[] = $line;
            }
        }

        return implode("\n", $preamble);
    }

    private function containsAnyKnownBankSignature(string $text): bool
    {
        $compact = strtolower(preg_replace('/\s+/', '', $text));

        foreach (self::BANK_SIGNATURES as $signatures) {
            foreach ($signatures as $sig) {
                if (str_contains($compact, str_replace(' ', '', $sig))) {
                    return true;
                }
            }
        }

        return false;
    }

    // Scans entire document so statements where PDF extractors place footer text before header text (like Zenith) still match regardless of name order
    private function verifyStatementOwnership(string $docText, string $userName): bool
    {
        $compactDoc = strtolower(preg_replace('/\s+/', '', $docText));

        return $this->namesMatch($userName, $compactDoc, true);
    }

    private function verifyBankInstitution(string $docText, string $institution): bool
    {
        $headerFooter = substr($docText, 0, 1500) . ' ' . substr($docText, -1000);
        $cleanText = strtolower(preg_replace('/\s+/', ' ', $headerFooter));
        $compactText = strtolower(preg_replace('/\s+/', '', $headerFooter));
        $instLower = strtolower(trim($institution));

        foreach (self::BANK_SIGNATURES as $bankKey => $signatures) {
            if (str_contains($instLower, $bankKey) || str_contains($bankKey, $instLower)) {
                foreach ($signatures as $sig) {
                    if (str_contains($cleanText, $sig) || str_contains($compactText, str_replace(' ', '', $sig))) {
                        return true;
                    }
                }

                return false;
            }
        }

        $words = array_filter(preg_split('/\s+/', $instLower), fn ($w) => strlen($w) >= 3 && ! in_array($w, ['bank', 'mfb', 'plc', 'ltd', 'wallet']));

        if (empty($words)) {
            return true;
        }

        foreach ($words as $word) {
            if (str_contains($cleanText, $word) || str_contains($compactText, $word)) {
                return true;
            }
        }

        return false;
    }

    private function namesMatch(string $registeredName, string $targetText, bool $isRawHeader = false): bool
    {
        $cleanTarget = $isRawHeader
            ? $targetText
            : strtolower(preg_replace('/\s+/', '', $targetText));

        $tokens = array_filter(
            preg_split('/\s+/', strtolower(trim($registeredName))),
            fn ($t) => strlen($t) >= 3
        );

        if (empty($tokens)) {
            return false;
        }

        $matchedTokens = 0;
        foreach ($tokens as $token) {
            if (str_contains($cleanTarget, $token)) {
                $matchedTokens++;
            }
        }

        $requiredMatches = count($tokens) >= 2 ? 2 : 1;

        return $matchedTokens >= $requiredMatches;
    }

    private function isSelfTransaction(string $narration, string $accountHolderName): bool
    {
        $compactNarration = strtolower(preg_replace('/\s+/', '', $narration));

        $tokens = array_values(array_filter(
            preg_split('/\s+/', strtolower(trim($accountHolderName))),
            fn ($t) => strlen($t) >= 3
        ));

        if (count($tokens) < 2) {
            return false;
        }

        $matchedTokens = 0;
        foreach ($tokens as $token) {
            if (str_contains($compactNarration, $token)) {
                $matchedTokens++;
            }
        }

        return $matchedTokens >= 2;
    }

    public function confirmImport(Request $request, Account $account)
    {
        if ($account->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.date' => 'required|date',
            'rows.*.title' => 'required|string|max:255',
            'rows.*.amount' => 'required|numeric|min:0.01',
            'rows.*.type' => 'required|in:income,expense,transfer',
            'rows.*.category' => 'required|string|max:100',
            'rows.*.is_unclear' => 'boolean',
            'closing_balance' => 'nullable|numeric',
        ]);

        $referenceName = $account->name ?: $request->user()->name;

        $existingSnapshot = $account->expenses()->get()->map(fn ($e) => [
            'date' => $e->date,
            'amount' => (float) $e->amount,
            'title' => $e->title,
        ])->toArray();

        $importedCount = 0;
        $skippedDuplicates = 0;
        $needsClarificationCount = 0;

        DB::transaction(function () use ($request, $account, $validated, $referenceName, $existingSnapshot, &$importedCount, &$skippedDuplicates, &$needsClarificationCount) {
            foreach ($validated['rows'] as $row) {
                $alreadyExists = collect($existingSnapshot)->contains(fn ($ex) =>
                    $ex['date'] === $row['date'] &&
                    abs($ex['amount'] - (float) $row['amount']) < 0.01 &&
                    $ex['title'] === $row['title']
                );

                if ($alreadyExists) {
                    $skippedDuplicates++;
                    continue;
                }

                if ($row['category'] === 'Self-Transfer' || $this->isSelfTransaction($row['title'], $referenceName)) {
                    $finalCategory = 'Self-Transfer';
                    $isUnclear = false;
                } else {
                    $smartCategory = self::classifyNarration($row['title']);
                    $isUnclear = $smartCategory === null && (bool) ($row['is_unclear'] ?? false);
                    $finalCategory = $smartCategory ?? ($isUnclear ? 'Uncategorized' : ucfirst(trim($row['category'])));
                }

                $request->user()->expenses()->create([
                    'account_id' => $account->id,
                    'title' => $row['title'],
                    'amount' => $row['amount'],
                    'type' => $row['type'],
                    'category' => $finalCategory,
                    'date' => $row['date'],
                    'is_excluded' => $isUnclear,
                ]);

                $importedCount++;
                if ($isUnclear) {
                    $needsClarificationCount++;
                }
            }

            if (isset($validated['closing_balance']) && $validated['closing_balance'] !== null) {
                $account->update(['balance' => $validated['closing_balance']]);
            } else {
                $totalIn = $account->expenses()->where('type', 'income')->sum('amount');
                $totalOut = $account->expenses()->where('type', 'expense')->sum('amount');
                $account->update(['balance' => $totalIn - $totalOut]);
            }
        });

        $dupMsg = $skippedDuplicates > 0 ? " ({$skippedDuplicates} duplicates skipped)." : '.';

        return response()->json([
            'message' => "Saved {$importedCount} transaction(s) into {$account->institution}{$dupMsg} {$needsClarificationCount} flagged for clarification.",
        ]);
    }

    public static function classifyNarration(string $text): ?string
    {
        $lower = strtolower(trim($text));

        // 1. Multi-purpose delivery apps & vague words always require user clarification
        $ambiguousKeywords = ['chowdeck', 'glovo', 'bolt food', 'heyfood'];
        foreach ($ambiguousKeywords as $amb) {
            if (str_contains($lower, $amb)) {
                return null;
            }
        }

        // Purely generic words with no item/recipient context (e.g., "Purchase", "POS Purchase")
        $cleanedGeneric = trim(preg_replace('/\b(pos|web|online|card|nip|trf|transfer|payment|purchase|debit|credit|via|from|to)\b/i', '', $lower));
        if ($cleanedGeneric === '' || strlen($cleanedGeneric) < 3) {
            return null;
        }

        // 2. Match known categories
        foreach (self::KEYWORDS as $category => $keywords) {
            foreach ($keywords as $kw) {
                if (strlen($kw) <= 4) {
                    if (preg_match('/\b' . preg_quote($kw, '/') . '\b/', $lower)) {
                        return $category;
                    }
                } elseif (str_contains($lower, $kw)) {
                    return $category;
                }
            }
        }

        if (str_contains($lower, 'salary') || str_contains($lower, 'payroll')) {
            return 'Salary';
        }
        if (str_contains($lower, 'loan repayment') || str_contains($lower, 'easemoni') || str_contains($lower, 'fairmoney') || str_contains($lower, 'carbon')) {
            return 'Loans & Bills';
        }
        if (str_contains($lower, 'misc') || str_contains($lower, 'miscellaneous')) {
            return 'Miscellaneous';
        }

        return null;
    }

    private function detectSlashDateOrder(string $text): string
    {
        preg_match_all('~\d{1,2}/\d{1,2}/\d{4}~', $text, $matches);
        $rawDates = reset($matches) ?: [];

        $dmyValidCount = 0;
        $mdyValidCount = 0;
        $today = now()->toDateString();

        foreach ($rawDates as $dateStr) {
            $parts = explode('/', (string) $dateStr);
            if (count($parts) !== 3) {
                continue;
            }

            list($p1, $p2, $p3) = $parts;
            $first = (int) $p1;
            $second = (int) $p2;
            $year = (int) $p3;

            if ($first > 12) {
                return 'DMY';
            }
            if ($second > 12) {
                return 'MDY';
            }

            if (checkdate($second, $first, $year)) {
                $dmyDate = sprintf('%04d-%02d-%02d', $year, $second, $first);
                if ($dmyDate <= $today) {
                    $dmyValidCount++;
                }
            }

            if (checkdate($first, $second, $year)) {
                $mdyDate = sprintf('%04d-%02d-%02d', $year, $first, $second);
                if ($mdyDate <= $today) {
                    $mdyValidCount++;
                }
            }
        }

        if ($mdyValidCount > $dmyValidCount) {
            return 'MDY';
        }

        return 'DMY';
    }

    private function parseStatementDate(string $rawDate, string $slashOrder = 'DMY'): ?string
    {
        $rawDate = trim($rawDate);

        if (str_contains($rawDate, '/')) {
            $parts = explode('/', $rawDate);

            if (count($parts) === 3) {
                list($p1, $p2, $p3) = $parts;
                $first = (int) $p1;
                $second = (int) $p2;
                $year = (int) $p3;

                if ($year < 100) {
                    $year += 2000;
                }

                if ($slashOrder === 'MDY') {
                    if (checkdate($first, $second, $year)) {
                        return sprintf('%04d-%02d-%02d', $year, $first, $second);
                    }
                } else {
                    if (checkdate($second, $first, $year)) {
                        return sprintf('%04d-%02d-%02d', $year, $second, $first);
                    }
                }
            }
        }

        $dt = DateTime::createFromFormat('d-M-Y', $rawDate)
            ?: DateTime::createFromFormat('d M Y', $rawDate)
            ?: DateTime::createFromFormat('Y-m-d', $rawDate)
            ?: DateTime::createFromFormat('d-m-Y', $rawDate);

        if ($dt) {
            return $dt->format('Y-m-d');
        }

        $ts = strtotime($rawDate);

        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function parseUniversalBankPdf(string $text, string $referenceName = ''): array
    {
        $slashOrder = $this->detectSlashDateOrder($text);

        $headerOpening = null;
        $headerClosing = null;
        $headerTotalDebit = null;
        $headerTotalCredit = null;

        if (preg_match('/Opening\sBalance[:\s₦]*([\d,]+\.\d{2})/i', $text, $m)) {
            $headerOpening = $this->parseMoney((string) end($m));
        }
        if (preg_match('/Closing\sBalance[:\s₦]*([\d,]+\.\d{2})/i', $text, $m)) {
            $headerClosing = $this->parseMoney((string) end($m));
        }
        if (preg_match('/Total\sDebit[:\s₦]*([\d,]+\.\d{2})/i', $text, $m)) {
            $headerTotalDebit = $this->parseMoney((string) end($m));
        }
        if (preg_match('/Total\sCredit[:\s₦]*([\d,]+\.\d{2})/i', $text, $m)) {
            $headerTotalCredit = $this->parseMoney((string) end($m));
        }

        if ($headerOpening === null && $headerClosing !== null && $headerTotalCredit !== null && $headerTotalDebit !== null) {
            $headerOpening = round($headerClosing - $headerTotalCredit + $headerTotalDebit, 2);
        }

        $rawLines = preg_split('/\r\n|\r|\n/', $text);
        $blocks = [];
        $currentBlock = '';

        foreach ($rawLines as $rawLine) {
            $line = trim(preg_replace('/\s+/', ' ', $rawLine));

            if ($line === '') {
                continue;
            }

            if (
                preg_match('/^page\s+\d+(\s+of\s+\d+)?$/i', $line) ||
                preg_match('/^(trans.?\sdate|value\sdate|posting\sdate|narration|description|particulars|debit|credit|balance)/i', $line) ||
                preg_match('/^(opening\sbalance|closing\sbalance|total\sdebit|total\scredit|total\s\(|account\snumber|account\sname|account\stype|currency)[:\s(]/i', $line) ||
                preg_match('/^\d+\s+debit\(s\)/i', $line) ||
                preg_match('/(www\.|http|\.com|\.ng|@|rc\s\d{5,}|licensed\s+by\s+the\s+central\s+bank|ndic|head\soffice|\+234|download\sapp|africa\'?s\sglobal\sbank|zenithdirect)/i', $line)
            ) {
                continue;
            }

            if (preg_match('~^(' . self::DATE_PATTERN . ')~', $line)) {
                if ($currentBlock !== '') {
                    $blocks[] = $currentBlock;
                }
                $currentBlock = $line;
            } elseif ($currentBlock !== '') {
                $currentBlock .= ' ' . $line;
            }
        }

        if ($currentBlock !== '') {
            $blocks[] = $currentBlock;
        }

        $rows = [];
        $runningBalance = $headerOpening;

        foreach ($blocks as $block) {
            if (! preg_match('~^(' . self::DATE_PATTERN . ')~', $block, $dateMatch)) {
                continue;
            }

            $rawDate = (string) reset($dateMatch);
            $parsedDate = $this->parseStatementDate($rawDate, $slashOrder);

            if (! $parsedDate) {
                continue;
            }

            if (stripos($block, 'OpeningBalance') !== false || stripos($block, 'Opening Balance') !== false) {
                if (preg_match('/([\d,]+\.\d{2})/', $block, $obMatch)) {
                    $runningBalance = $this->parseMoney((string) end($obMatch));
                }
                continue;
            }

            preg_match_all('/(' . self::AMOUNT_PATTERN . ')/', $block, $amountMatches);
            $amounts = end($amountMatches);

            if (! is_array($amounts) || empty($amounts)) {
                continue;
            }

            $type = null;
            $txAmount = 0;
            $rowBalance = null;
            $uncertainDirection = false;

            if (preg_match('/(\d{1,3}(?:,\d{3})*\.\d{2})\s+--\s+(\d{1,3}(?:,\d{3})*\.\d{2})/', $block, $m)) {
                $type = 'expense';
                $txAmount = $this->parseMoney((string) next($m));
                $rowBalance = $this->parseMoney((string) next($m));
            } elseif (preg_match('/--\s+(\d{1,3}(?:,\d{3})*\.\d{2})\s+(\d{1,3}(?:,\d{3})*\.\d{2})/', $block, $m)) {
                $type = 'income';
                $txAmount = $this->parseMoney((string) next($m));
                $rowBalance = $this->parseMoney((string) next($m));
            } elseif (count($amounts) >= 2) {
                $lastRaw = (string) end($amounts);
                $prevRaw = (string) prev($amounts);

                $rowBalance = $this->parseMoney($lastRaw);
                $txAmount = $this->parseMoney($prevRaw);

                if (str_starts_with(trim($prevRaw), '-')) {
                    $type = 'expense';
                } elseif ($runningBalance !== null) {
                    $delta = round($rowBalance - $runningBalance, 2);

                    if ($delta > 0) {
                        $type = 'income';
                    } elseif ($delta < 0) {
                        $type = 'expense';
                    } else {
                        continue;
                    }
                } else {
                    $type = 'expense';
                    $uncertainDirection = true;
                }
            }

            if (! $type || $txAmount <= 0) {
                continue;
            }

            if ($rowBalance !== null) {
                $runningBalance = $rowBalance;
            }

            $narration = preg_replace('~^(?:' . self::DATE_PATTERN . ')\s*(?:\d{2}:\d{2}:\d{2}\s*)?(?:' . self::DATE_PATTERN . ')?\s*~', '', $block);
            $narration = preg_replace('/(?:--\s+)?' . self::AMOUNT_PATTERN . '.*$/', '', (string) $narration);
            $narration = preg_replace('/\b[A-Za-z0-9]{12,}\b/', '', (string) $narration);
            $narration = trim(preg_replace('/\s+/', ' ', (string) $narration));

            if ($narration === '') {
                $narration = 'Bank Transaction';
            }

            $lowerNarration = strtolower($narration);

            if (str_contains($lowerNarration, 'owealth') && ! str_contains($lowerNarration, 'interest')) {
                $type = 'transfer';
                $category = 'Internal Wallet Sweep';
                $isUnclear = false;
            } elseif ($referenceName !== '' && $this->isSelfTransaction($narration, $referenceName)) {
                $category = 'Self-Transfer';
                $isUnclear = $uncertainDirection;
            } else {
                $detectedCategory = self::classifyNarration($narration);
                $isUnclear = $detectedCategory === null || $uncertainDirection;
                $category = $detectedCategory ?? 'Uncategorized';
            }

            $rows[] = [
                'date' => $parsedDate,
                'title' => substr($narration, 0, 180),
                'amount' => $txAmount,
                'type' => $type,
                'balance_after' => $rowBalance,
                'category' => $category,
                'is_unclear' => $isUnclear,
                'selected' => $type !== 'transfer',
            ];
        }

        return [
            'rows' => $rows,
            'opening_balance' => $headerOpening,
            'closing_balance' => $headerClosing ?? $runningBalance,
            'statement_total_debit' => $headerTotalDebit,
            'statement_total_credit' => $headerTotalCredit,
        ];
    }

    private function parseCsvFile(string $path, string $referenceName = ''): array
    {
        $rawContent = file_get_contents($path) ?: '';
        $slashOrder = $this->detectSlashDateOrder($rawContent);

        $file = fopen($path, 'r');
        if (! $file) {
            return ['rows' => [], 'opening_balance' => null, 'closing_balance' => null];
        }

        $headerMap = null;

        while (($row = fgetcsv($file)) !== false) {
            $normalized = array_map(fn ($col) => strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', (string) $col))), $row);

            $dateIdx = $this->findColumnIndex($normalized, ['date', 'transaction date', 'trans date', 'value date']);
            $descIdx = $this->findColumnIndex($normalized, ['narration', 'description', 'remarks', 'details']);
            $debitIdx = $this->findColumnIndex($normalized, ['debit', 'withdrawal', 'dr']);
            $creditIdx = $this->findColumnIndex($normalized, ['credit', 'deposit', 'cr']);
            $balIdx = $this->findColumnIndex($normalized, ['balance', 'running balance', 'balance after']);

            if ($dateIdx !== null && ($debitIdx !== null || $creditIdx !== null)) {
                $headerMap = compact('dateIdx', 'descIdx', 'debitIdx', 'creditIdx', 'balIdx');
                break;
            }
        }

        $rows = [];
        $lastBalance = null;

        if ($headerMap) {
            while (($row = fgetcsv($file)) !== false) {
                $rawDate = trim((string) ($row[$headerMap['dateIdx']] ?? ''));
                $parsedDate = $this->parseStatementDate($rawDate, $slashOrder);

                if (! $rawDate || ! $parsedDate) {
                    continue;
                }

                $narration = $headerMap['descIdx'] !== null ? trim((string) ($row[$headerMap['descIdx']] ?? 'Bank Transaction')) : 'Bank Transaction';
                $debit = $headerMap['debitIdx'] !== null ? $this->parseMoney((string) ($row[$headerMap['debitIdx']] ?? '0')) : 0;
                $credit = $headerMap['creditIdx'] !== null ? $this->parseMoney((string) ($row[$headerMap['creditIdx']] ?? '0')) : 0;
                $bal = $headerMap['balIdx'] !== null ? $this->parseMoney((string) ($row[$headerMap['balIdx']] ?? '0')) : null;

                if ($credit > 0 || $debit > 0) {
                    if ($bal !== null) {
                        $lastBalance = $bal;
                    }

                    if ($referenceName !== '' && $this->isSelfTransaction($narration, $referenceName)) {
                        $category = 'Self-Transfer';
                        $isUnclear = false;
                    } else {
                        $detected = self::classifyNarration($narration);
                        $category = $detected ?? 'Uncategorized';
                        $isUnclear = $detected === null;
                    }

                    $rows[] = [
                        'date' => $parsedDate,
                        'title' => substr($narration ?: 'Bank Transaction', 0, 180),
                        'amount' => $credit > 0 ? $credit : $debit,
                        'type' => $credit > 0 ? 'income' : 'expense',
                        'balance_after' => $bal,
                        'category' => $category,
                        'is_unclear' => $isUnclear,
                        'selected' => true,
                    ];
                }
            }
        }

        fclose($file);

        return ['rows' => $rows, 'opening_balance' => null, 'closing_balance' => $lastBalance];
    }

    public function reconcile(Request $request, Account $account)
    {
        if ($account->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'actual_balance' => 'required|numeric|min:0',
            'auto_log' => 'boolean',
        ]);

        $trackedBalance = (float) $account->balance;
        $actualBalance = (float) $validated['actual_balance'];
        $difference = $trackedBalance - $actualBalance;

        if (abs($difference) < 0.01) {
            return response()->json([
                'status' => 'matched',
                'message' => "Your {$account->institution} balance matches 100%! No missing transactions found.",
                'discrepancy' => 0,
            ]);
        }

        if (! $request->boolean('auto_log')) {
            if ($difference > 0) {
                return response()->json([
                    'status' => 'missing_expense',
                    'message' => "Discrepancy detected! Your actual {$account->institution} balance (₦" . number_format($actualBalance, 2) . ") is ₦" . number_format($difference, 2) . " lower than tracked (₦" . number_format($trackedBalance, 2) . ").",
                    'discrepancy' => $difference,
                ]);
            }

            $unloggedIncome = abs($difference);

            return response()->json([
                'status' => 'missing_income',
                'message' => "Discrepancy detected! Your actual {$account->institution} balance (₦" . number_format($actualBalance, 2) . ") is ₦" . number_format($unloggedIncome, 2) . " higher than tracked.",
                'discrepancy' => $unloggedIncome,
            ]);
        }

        DB::transaction(function () use ($request, $account, $difference, $actualBalance) {
            $request->user()->expenses()->create([
                'account_id' => $account->id,
                'title' => $difference > 0
                    ? "Missing Expense ({$account->institution}) - Needs Clarification"
                    : "Missing Inflow ({$account->institution}) - Needs Clarification",
                'amount' => abs($difference),
                'type' => $difference > 0 ? 'expense' : 'income',
                'category' => 'Uncategorized',
                'date' => now()->toDateString(),
                'is_excluded' => true,
            ]);

            $account->update(['balance' => $actualBalance]);
        });

        return response()->json([
            'status' => 'reconciled',
            'message' => "Reconciled! Logged adjustment of ₦" . number_format(abs($difference), 2) . " and flagged it for clarification.",
        ]);
    }

    public function destroy(Request $request, Account $account)
    {
        if ($account->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $account->delete();

        return response()->json(['message' => 'Account deleted successfully']);
    }

    private function findColumnIndex(array $headers, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $headers, true);
            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }

    private function parseMoney(string $value): float
    {
        $cleaned = preg_replace('/[^0-9.]/', '', $value);

        return (float) ($cleaned ?: 0);
    }
}