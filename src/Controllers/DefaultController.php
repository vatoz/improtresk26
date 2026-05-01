<?php
namespace App\Controllers;

use App\Models\ProgramItem;
use App\Models\ProgramInfo;
use App\Models\ChildrenItem;
use App\Models\FAQ;
use App\Models\Person;
use App\Models\StaticBlock;
use vplacek\QRPlatba\QRPlatba;

class DefaultController extends BaseController
{
    public function index()
    {
        $registrationStart = $_ENV['REGISTRATION_START'] ?? null;
        echo $this->twig->render('pages/home.twig', [
            'user' => $this->getCurrentUser(),
            'active_page' => 'home',
            'registration_start' => $registrationStart,
            'static' => (new StaticBlock)->getByPrefix($this->db,'home'),
        ]);
    }


    
    public function program()
    {
        $programInfo = ProgramInfo::getGroupedByGroup($this->db);
        
       
        echo $this->twig->render('pages/program.twig', [
            'user' => $this->getCurrentUser(),
            'active_page' => 'program',       
            'program_info' => $programInfo,
        ]);
    }

    public function harmonogram()
    {
        // Get program items grouped by date and track for concurrent display
        $programItems = ProgramItem::getGroupedByDateAndTrack($this->db);
        $programInfoMap = ProgramInfo::getMapByProgramItemId($this->db);

        echo $this->twig->render('pages/harmonogram.twig', [
            'user' => $this->getCurrentUser(),
            'active_page' => 'harmonogram',
            'program_items' => $programItems,
            'program_info_map' => $programInfoMap,
        ]);
    }

    public function info()
    {
        echo $this->twig->render('pages/info.twig', [
            'user' => $this->getCurrentUser(),
            'active_page' => 'info',
            'static' => (new StaticBlock)->getByPrefix($this->db,'info'),
        ]);
    }

    public function faq()
    {
        // Get FAQ items grouped by category
        $faqItems = FAQ::getGroupedByCategory($this->db);

        echo $this->twig->render('pages/faq.twig', [
            'user' => $this->getCurrentUser(),
            'active_page' => 'faq',
            'faq_items' => $faqItems
        ]);
    }

    public function contact()
    {

        $people = Person::getAll($this->db);
        echo $this->twig->render('pages/contact.twig', [
            'user' => $this->getCurrentUser(),
            'active_page' => 'contact',
            'people'=> $people,
            //'static' =>  [['name'=>'contact_org','content'=>'ahoj světe']]            
            'static' => (new StaticBlock)->getByPrefix($this->db,'contact')
        ]);
    }

    public function hero()
    {
        $this->handleHeroPage(1, 'hero');
    }

    public function heroine()
    {
        $this->handleHeroPage(2, 'heroine');
    }

    private function handleHeroPage(int $heroValue, string $page)
    {
        $this->requireAuth();

        $secret = $_GET['secret'] ?? '';
        if ($secret !== ($_ENV['HERO_SECRET'] ?? '')) {
            http_response_code(403);
            echo $this->twig->render('pages/hero.twig', [
                'user' => $this->getCurrentUser(),
                'message' => null,
                'page' => $page,
            ]);
            return;
        }

        $user = $this->getCurrentUser();
        if ($user) {
            \App\Models\User::update($this->db, $user['id'], ['hero' => $heroValue]);
            $_SESSION['user']['hero']= $heroValue;
        }

        echo $this->twig->render('pages/hero.twig', [
            'user' => $user,
            'message' => $heroValue === 1 ? 'Jsi náš hrdina!' : 'Jsi naše hrdinka!',
            'page' => $page,
        ]);
    }

    public function qrPlatba()
    {
        $castka = filter_input(INPUT_GET, 'castka', FILTER_VALIDATE_FLOAT);
        $vs     = filter_input(INPUT_GET, 'vs', FILTER_SANITIZE_NUMBER_INT);

        if ($castka === false || $castka === null || $vs === null || $vs === '') {
            http_response_code(400);
            echo 'Chybí parametry castka nebo vs.';
            return;
        }

        $paymentConfig = payment_config();
        $qrPlatba = new QRPlatba();
        $qrPlatba->setIban($paymentConfig['iban'])
            ->setAmount((float) $castka)
            ->setScale(5)
            ->setVariableSymbol((string) $vs);

        header('Content-Type: image/png');
        echo $qrPlatba->generateQr();
    }

    public function medailonky()
    {
        $people = Person::getGroupedBySection($this->db);

        echo $this->twig->render('pages/medailonky.twig', [
            'user' => $this->getCurrentUser(),
            'active_page' => 'medailonky',
            'people' => $people,
        ]);
    }

    public function children()
    {
        $items = ChildrenItem::getAll($this->db);

        echo $this->twig->render('pages/children.twig', [
            'user' => $this->getCurrentUser(),
            'active_page' => 'children',
            'items' => $items,
            'static' => (new StaticBlock)->getByPrefix($this->db,'children')
        ]);
    }

    public function childrenCheckout()
    {
        $items = ChildrenItem::getAll($this->db);

        // Load children-type tickets grouped by children_item_id
        $ticketsByItem = [];
        $tStmt = $this->db->query("
            SELECT id, name, type, price, children_item_id
            FROM tickets
            WHERE is_active = 1
              AND type IN ('child', 'adult', 'family')
              AND children_item_id IS NOT NULL
            ORDER BY children_item_id, FIELD(type, 'child', 'adult', 'family')
        ");
        foreach ($tStmt->fetchAll(\PDO::FETCH_ASSOC) as $t) {
            $ticketsByItem[$t['children_item_id']][] = $t;
        }
        foreach ($items as &$item) {
            $item['tickets'] = $ticketsByItem[$item['id']] ?? [];
        }
        unset($item);

        $errors    = [];
        $payment   = null;
        $submitted = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_validate('children-checkout', $_POST['_csrf'] ?? null);

            $name  = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if (!$name) {
                $errors[] = 'Jméno je povinné.';
            }

            $selected = [];
            foreach ($_POST['tickets'] ?? [] as $tid => $qty) {
                $qty = (int) $qty;
                if ($qty > 0) {
                    $selected[(int) $tid] = $qty;
                }
            }
            if (empty($selected)) {
                $errors[] = 'Prosím vyberte alespoň jednu vstupenku.';
            }

            if (empty($errors)) {
                // Find or create user
                if ($email) {
                    $existing = \App\Models\User::findByEmail($this->db, $email);
                    if ($existing) {
                        $userId = (int) $existing['id'];
                        if ($phone && empty($existing['phone'])) {
                            \App\Models\User::update($this->db, $userId, ['phone' => $phone]);
                        }
                    } else {
                        $hash   = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                        $userId = \App\Models\User::create($this->db, $name, $email, $hash);
                        if ($phone) {
                            \App\Models\User::update($this->db, $userId, ['phone' => $phone]);
                        }
                    }
                } else {
                    $placeholder = 'guest_' . time() . '_' . bin2hex(random_bytes(3)) . '@improtresk.cz';
                    $hash        = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                    $userId      = \App\Models\User::create($this->db, $name, $placeholder, $hash);
                    if ($phone) {
                        \App\Models\User::update($this->db, $userId, ['phone' => $phone]);
                    }
                }

                // Validate tickets and create purchases
                $totalAmount      = 0.0;
                $purchasedTickets = [];
                foreach ($selected as $ticketId => $qty) {
                    $vStmt = $this->db->prepare("
                        SELECT id, name, price FROM tickets
                        WHERE id = ? AND is_active = 1 AND type IN ('child','adult','family')
                        LIMIT 1
                    ");
                    $vStmt->execute([$ticketId]);
                    $ticketRow = $vStmt->fetch(\PDO::FETCH_ASSOC);
                    if ($ticketRow) {
                        $this->db->prepare("
                            INSERT INTO purchases (user_id, item_type, item_id, quantity, payment_status)
                            VALUES (?, 'ticket', ?, ?, 'pending')
                        ")->execute([$userId, $ticketId, $qty]);
                        $lineTotal         = (float) $ticketRow['price'] * $qty;
                        $totalAmount      += $lineTotal;
                        $purchasedTickets[] = [
                            'name'     => $ticketRow['name'],
                            'quantity' => $qty,
                            'price'    => (float) $ticketRow['price'],
                            'total'    => $lineTotal,
                        ];
                    }
                }

                if ($email) {
                    // Track awaiting payment
                    $this->db->prepare("UPDATE users SET awaiting_payment = awaiting_payment + ? WHERE id = ?")->execute([$totalAmount, $userId]);
                }

                
                // Generate QR code
                $cfg = payment_config();
                $vs  = $userId;
                $qr  = new QRPlatba();
                $qr->setIban($cfg['iban'])
                   ->setAmount($totalAmount)
                   ->setScale(5)
                   ->setVariableSymbol($vs);

                $payment = [
                    'readable'        => $cfg['readable'],
                    'variable_symbol' => $vs,
                    'amount'          => $totalAmount,
                    'currency'        => $cfg['currency'],
                    'message'         => $cfg['message'],
                    'qr'              => 'data:image/png;base64,' . base64_encode($qr->generateQr()),
                    'tickets'         => $purchasedTickets,
                ];
                $submitted = true;
            }
        }

        echo $this->twig->render('pages/children-checkout.twig', [
            'user'        => $this->getCurrentUser(),
            'active_page' => 'children',
            'items'       => $items,
            'static'      => (new StaticBlock)->getByPrefix($this->db, 'children'),
            'errors'      => $errors,
            'payment'     => $payment,
            'submitted'   => $submitted,
            'csrf'        => csrf_token('children-checkout'),
        ]);
    }
}
