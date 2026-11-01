<?php

declare(strict_types=1);

require_once __DIR__ . '/password_reset.php';
require_once __DIR__ . '/trainer_password_reset.php';

/**
 * Both the fragment preflight and password submission use the current database state.
 * A successful preflight is not authority to write: Consume checks again under lock.
 * @return array{errors:list<string>,success:bool,showForm:bool,token:string}
 */
function passwordResetFormSubmit(PDO $pdo, bool $trainerReset, array $input, bool $csrfValid): array
{
    $state = ['errors' => [], 'success' => false, 'showForm' => false, 'token' => ''];
    if (!$csrfValid) {
        $state['errors'][] = 'Neplatný bezpečnostní token.';
        return $state;
    }
    $action = (string)($input['action'] ?? 'consume');
    if (!in_array($action, ['check', 'consume'], true)) {
        $state['errors'][] = 'Neplatný požadavek na obnovu hesla.';
        return $state;
    }
    $token = trim((string)($input['token'] ?? ''));
    try {
        $reset = $trainerReset ? trainerPasswordResetLookup($pdo, $token) : passwordResetLookup($pdo, $token);
        if ($reset === null) {
            $state['errors'][] = 'Odkaz není platný nebo už byl použit.';
            return $state;
        }
        $state['showForm'] = true;
        $state['token'] = $token;
        if ($action === 'check') {
            return $state;
        }
        $password = (string)($input['heslo'] ?? '');
        if ($password !== (string)($input['heslo2'] ?? '')) {
            $state['errors'][] = 'Hesla se neshodují.';
            return $state;
        }
        $result = $trainerReset
            ? trainerPasswordResetConsume($pdo, $token, $password)
            : passwordResetConsume($pdo, $token, $password);
        $state['showForm'] = false;
        $state['token'] = '';
        if ($result === null) {
            $state['errors'][] = 'Odkaz není platný nebo už byl použit.';
        } else {
            $state['success'] = true;
        }
    } catch (InvalidArgumentException $exception) {
        $state['errors'][] = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('Password reset consume failed: ' . $exception->getMessage());
        $state['errors'][] = 'Heslo nyní nelze změnit. Zkuste to znovu.';
        $state['showForm'] = false;
        $state['token'] = '';
    }
    return $state;
}
