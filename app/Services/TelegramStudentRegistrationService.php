<?php
declare(strict_types=1);
namespace App\Services;

use App\Helpers\FormatHelper;
use App\Repositories\NotificationRepository;
use PDO;

final class TelegramStudentRegistrationService
{
    public function __construct(private readonly PDO $pdo, private readonly TelegramService $telegram, private readonly StudentService $students) {}

    public function handle(array $update): bool
    {
        $callback = $update['callback_query'] ?? null;
        $message = $callback['message'] ?? $update['message'] ?? [];
        $chat = (string) ($message['chat']['id'] ?? '');
        $user = (string) (($callback['from']['id'] ?? $message['from']['id']) ?? '');
        if ($chat !== $this->telegram->chatId() || $user === '' || $user === '0') { return false; }
        $text = trim((string) ($message['text'] ?? ''));
        $action = (string) ($callback['data'] ?? '');
        if ($callback !== null && !str_starts_with($action, 'new|')) { return false; }
        $lock = 'new_student_' . substr(hash('sha256', $chat . ':' . $user), 0, 40);
        $stmt = $this->pdo->prepare('SELECT GET_LOCK(?, 0)');
        $stmt->execute([$lock]);
        if (!(int) $stmt->fetchColumn()) { return true; }
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM telegram_student_drafts WHERE chat_id=? AND user_id=? AND expires_at>?');
            $stmt->execute([$chat, $user, time()]);
            $draft = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($callback === null && preg_match('~^/nowa(?:@[A-Za-z0-9_]+)?$~u', $text)) {
                $draft = ['chat_id'=>$chat, 'user_id'=>$user, 'nonce'=>bin2hex(random_bytes(8)), 'step'=>'first_name', 'data_json'=>'{}', 'prompt_id'=>0];
                $this->persist($draft);
                $this->prompt($draft);
                return true;
            }
            if ($callback === null && preg_match('~^/anuluj(?:@[A-Za-z0-9_]+)?$~u', $text)) {
                if ($draft) { $draft['step']='cancelled'; $draft['data_json']='{}'; $this->persist($draft); }
                if ($draft) { $this->render($draft, 'Anulowano dodawanie uczennicy.'); }
                return true;
            }
            if ($callback !== null) {
                $parts = explode('|', $action);
                if (!$draft || ($parts[1] ?? '') !== $draft['nonce'] || in_array($draft['step'], ['done','cancelled'], true)) {
                    $this->telegram->answerCallbackQuery($callback['id'], 'Formularz wygasł lub należy do innej osoby. Użyj /nowa.');
                    return true;
                }
                $this->telegram->answerCallbackQuery($callback['id']);
                $choice = $parts[2] ?? '';
                $data = json_decode($draft['data_json'], true);
                if ($choice === 'cancel') {
                    $draft['step']='cancelled'; $draft['data_json']='{}'; $this->persist($draft);
                    if ($draft) { $this->render($draft, 'Anulowano dodawanie uczennicy.'); }
                    return true;
                }
                if ($choice === 'back') {
                    $draft['step'] = match ($draft['step']) { 'last_name'=>'first_name', 'birth_date'=>'last_name', 'phone'=>'birth_date', 'group'=>'phone', 'subscription'=>'group', 'confirm'=>'subscription', default=>'first_name' };
                } elseif ($choice === 'restart') {
                    $draft['nonce']=bin2hex(random_bytes(8)); $draft['step']='first_name'; $draft['data_json']='{}';
                } elseif (in_array($draft['step'], ['group','subscription'], true) && $choice === $draft['step']) {
                    $options=$this->options($draft['step']);
                    $id=(int)($parts[3] ?? 0);
                    if (!isset($options[$id])) { $this->prompt($draft, 'Ta opcja nie jest już dostępna.'); return true; }
                    $key=$draft['step']==='group' ? 'student_group_id' : 'subscription_id';
                    $data[$key]=$id;
                    $draft['data_json']=json_encode($data, JSON_UNESCAPED_UNICODE);
                    $draft['step']=$draft['step']==='group' ? 'subscription' : 'confirm';
                } elseif ($draft['step']==='confirm' && $choice==='save') {
                    $this->save($draft, $data);
                    return true;
                } else { return true; }
                $this->persist($draft); $this->prompt($draft);
                return true;
            }
            if (!$draft || !in_array($draft['step'], ['first_name','last_name','birth_date','phone'], true) || str_starts_with($text,'/')) { return false; }
            if (isset($message['reply_to_message']) && (int) $message['reply_to_message']['message_id'] !== (int) $draft['prompt_id']) { return false; }
            $previous = json_decode($draft['data_json'], true);
            if ((int) ($message['message_id'] ?? 0) > 0 && (int) $message['message_id'] <= (int) ($previous['_last_input_id'] ?? 0)) { return true; }
            if ($text==='' || mb_strlen($text)>100) { $this->prompt($draft,'Wpisz od 1 do 100 znaków.'); return true; }
            if ($draft['step']==='phone' && strlen(FormatHelper::phoneDigits($text))!==9) {
                $this->prompt($draft,'Telefon musi zawierać 9 cyfr (możesz dodać +48).'); return true;
            }
            if ($draft['step'] === 'birth_date') {
                $format = preg_match('/^\d{2}\.\d{2}\.\d{4}$/D', $text) ? 'd.m.Y' : 'Y-m-d';
                $zone = new \DateTimeZone('Europe/Warsaw');
                $date = \DateTimeImmutable::createFromFormat('!' . $format, $text, $zone);
                if (!$date || $date->format($format) !== $text || $date > new \DateTimeImmutable('today', $zone) || (int) $date->format('Y') < 1900) {
                    $this->prompt($draft, 'Podaj prawidłową datę urodzenia, np. 15.03.2010. Data nie może być w przyszłości.');
                    return true;
                }
                $text = $date->format('Y-m-d');
            }
            $data=json_decode($draft['data_json'],true);
            $data['_last_input_id']=(int) ($message['message_id'] ?? 0);
            $data[$draft['step']]=$text;
            $draft['data_json']=json_encode($data,JSON_UNESCAPED_UNICODE);
            $draft['step']=match($draft['step']) {'first_name'=>'last_name','last_name'=>'birth_date','birth_date'=>'phone','phone'=>'group'};
            $this->persist($draft); $this->prompt($draft);
            return true;
        } finally {
            $this->pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
        }
    }

    private function options(string $step): array
    {
        $sql=$step==='group' ? "SELECT id,name FROM student_groups WHERE status='active' AND is_visible=1 ORDER BY sort_order,name" : "SELECT id,CONCAT(name,' · ',lessons_count,' zaj. · ',price,' zł') AS name FROM subscriptions WHERE status='active' ORDER BY sort_order,id";
        $result=[];
        foreach($this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) { $result[(int)$row['id']]=$row['name']; }
        return $result;
    }

    private function persist(array $draft): void
    {
        $this->pdo->prepare('INSERT INTO telegram_student_drafts (chat_id,user_id,nonce,step,data_json,prompt_id,expires_at) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE nonce=VALUES(nonce),step=VALUES(step),data_json=VALUES(data_json),prompt_id=VALUES(prompt_id),expires_at=VALUES(expires_at)')
            ->execute([$draft['chat_id'],$draft['user_id'],$draft['nonce'],$draft['step'],$draft['data_json'],$draft['prompt_id'],time()+86400]);
    }

    private function render(array $draft, string $text, array $rows = []): void
    {
        $markup = ['inline_keyboard' => $rows];
        if ((int) $draft['prompt_id'] > 0) {
            try {
                $this->telegram->editMessageText($draft['chat_id'], (int) $draft['prompt_id'], $text, $markup);
                return;
            } catch (\RuntimeException $error) {
                if (str_contains($error->getMessage(), 'message is not modified')) { return; }
                if (!str_contains($error->getMessage(), 'message to edit not found') && !str_contains($error->getMessage(), "message can't be edited")) { throw $error; }
            }
        }
        $sent = $this->telegram->sendMessageToChat($draft['chat_id'], $text, $markup);
        $draft['prompt_id'] = (int) ($sent['result']['message_id'] ?? 0);
        $this->persist($draft);
    }

    private function prompt(array $draft, string $error = ''): void
    {
        $step = $draft['step']; $prefix = 'new|' . $draft['nonce'] . '|';
        $data = json_decode($draft['data_json'], true);
        $steps = ['first_name','last_name','birth_date','phone','group','subscription','confirm'];
        $index = array_search($step, $steps, true);
        $text = '<b>Nowa uczennica · ' . ($index + 1) . '/7</b>' . "\n";
        $text .= implode(' ', array_map(static fn ($i) => $i < $index ? '✓' : ($i === $index ? '●' : '○'), range(0,6))) . "\n\n";
        $fields = ['first_name'=>'Imię', 'last_name'=>'Nazwisko', 'birth_date'=>'Data urodzenia', 'phone'=>'Telefon'];
        foreach ($fields as $key => $label) {
            if (!empty($data[$key])) { $text .= htmlspecialchars($label . ': ' . ($key === 'birth_date' ? (new \DateTimeImmutable($data[$key]))->format('d.m.Y') : $data[$key]), ENT_QUOTES, 'UTF-8') . "\n"; }
        }
        if (!empty($data['student_group_id'])) { $text .= 'Grupa: ' . htmlspecialchars($this->options('group')[$data['student_group_id']] ?? 'Niedostępna', ENT_QUOTES, 'UTF-8') . "\n"; }
        if (!empty($data['subscription_id'])) { $text .= 'Abonament: ' . htmlspecialchars($this->options('subscription')[$data['subscription_id']] ?? 'Niedostępny', ENT_QUOTES, 'UTF-8') . "\n"; }
        $text .= "\n" . ($error !== '' ? '⚠️ ' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "\n" : '');
        $rows = [];
        if (in_array($step, ['first_name','last_name','birth_date','phone'], true)) {
            $text .= match($step) { 'first_name'=>'Wpisz imię uczennicy.', 'last_name'=>'Wpisz nazwisko uczennicy.', 'birth_date'=>'Wpisz datę urodzenia (DD.MM.RRRR), np. 15.03.2010.', 'phone'=>'Wpisz numer telefonu uczennicy.' };
            $text .= "\nWyślij zwykłą wiadomość w czacie — bez funkcji Odpowiedz.";
        } elseif (in_array($step, ['group','subscription'], true)) {
            $text .= $step === 'group' ? 'Wybierz grupę:' : 'Wybierz abonament:';
            foreach ($this->options($step) as $id => $label) { $rows[] = [['text'=>$label, 'callback_data'=>$prefix.$step.'|'.$id]]; }
            if ($rows === []) { $text .= "\nBrak aktywnych opcji. Dodaj je w ustawieniach CRM."; }
        } else {
            $text .= "Sprawdź dane i zapisz uczennicę.\nStatus: aktywna. Data dołączenia: dzisiaj.\nZapis nie dodaje płatności.";
            $rows[] = [['text'=>'✅ Zapisz uczennicę', 'callback_data'=>$prefix.'save']];
            $rows[] = [['text'=>'✏️ Popraw dane', 'callback_data'=>$prefix.'restart']];
        }
        $navigation = [];
        if ($step !== 'first_name') { $navigation[] = ['text'=>'← Wstecz', 'callback_data'=>$prefix.'back']; }
        $navigation[] = ['text'=>'Anuluj', 'callback_data'=>$prefix.'cancel'];
        $rows[] = $navigation;
        $this->render($draft, $text, $rows);
    }

    private function save(array $draft, array $data): void
    {
        if (!isset($this->options('group')[$data['student_group_id']], $this->options('subscription')[$data['subscription_id']])) {
            $draft['step']='group'; $this->persist($draft); $this->prompt($draft,'Grupa lub abonament nie jest już dostępny. Wybierz ponownie.'); return;
        }
        $data['status']='active'; $data['enrollment_date']=(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Warsaw')))->format('Y-m-d');
        $errors=$this->students->save($data,null,function(int $id) use ($draft,$data): void {
            $draft['step']='done'; $draft['data_json']=json_encode(['student_id'=>$id]); $this->persist($draft);
            (new NotificationRepository($this->pdo))->createOnce(['title'=>'Nowa uczennica z Telegrama','message'=>$data['first_name'].' '.$data['last_name'].' — dodano uczennicę, grupę i abonament.','type'=>'success','related_entity_type'=>'student','related_entity_id'=>$id]);
        });
        if ($errors!==[]) { $this->prompt($draft,'Nie udało się zapisać. Sprawdź dane i spróbuj ponownie.'); return; }
        $draft['step']='done';
        $draft['data_json']='{}';
        $this->render($draft,'✅ <b>Zapisano uczennicę</b>' . "\n" . htmlspecialchars($data['first_name'].' '.$data['last_name'], ENT_QUOTES, 'UTF-8') . "\nJest już widoczna w CRM → Uczennice.");
    }
}
