<?php
require_once '../include/accounts.inc.php';
require_once '../include/transfers.inc.php';
require_once '../include/sessiontimeout.inc.php';

$user = getAuthenticatedUser();

if (!$user) {
    header('Location: ../login.php');
    exit();
}

$accounts = getAccounts($user);
$readyToTransfer = true;

if (!isset($_POST['senderAccountNumber']) || empty($_POST['senderAccountNumber']) || !isAccountNumberValid($_POST['senderAccountNumber'])) {
    $readyToTransfer = false;
}

if (!isset($_POST['receiverAccountNumber']) || empty($_POST['receiverAccountNumber']) || !isAccountNumberValid($_POST['receiverAccountNumber'])) {
    $readyToTransfer = false;
}

if (!isset($_POST['amount']) || empty($_POST['amount']) || !filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT)) {
    $readyToTransfer = false;
}

if ($readyToTransfer) {
    $senderAccountNumber = str_replace('-', '', sanitiseInput($_POST['senderAccountNumber']));
    $receiverAccountNumber = str_replace('-', '', sanitiseInput($_POST['receiverAccountNumber']));
    $amountValue = sanitiseInput($_POST['amount']);
    $amount = new Currency($amountValue);

    $senderAccount = getAccount($senderAccountNumber);
    $receiverAccount = getAccount($receiverAccountNumber);

    if (performTransfer($senderAccount, $receiverAccount, $amount)) {
        header('Location: transferSuccess.php');
        exit();
    } else {
        header('Location: transferError.php');
        exit();
    }
}

$accounts = getAccounts($user);

?>
<!DOCTYPE html>
<!--
To change this license header, choose License Headers in Project Properties.
To change this template file, choose Tools | Templates
and open the template in the editor.
-->
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title></title>
    <?php include '../include/imports.inc.php' ?>
</head>

<body>
<?php include '../include/navbar.inc.php' ?>
<header class="jumbotron text-center">
    <h1 class="display-4">Transfer</h1>
</header>

<main class="container">
    <form method="POST">
        <p class="lead">Sending account</p>

        <div class="dropdown account-dropdown mb-3" id="senderDropdown">
            <button type="button" class="btn btn-light dropdown-toggle" id="senderDropdownButton"
                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                Select sending account
            </button>
            <div class="dropdown-menu" aria-labelledby="senderDropdownButton">
                <span class="dropdown-header">My accounts</span>
                <?php
                foreach ($accounts as $account) {
                    echo '<a class="dropdown-item" href="#" data-accountNumber="' . $account->accountNumber . '">' . $account->accountName . '</a>';
                }
                ?>
            </div>
        </div>

        <div class="form-group mb-3 d-none">
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text" id="account-number-from">Send from</span>
                </div>
                <input type="text" class="form-control" aria-label="Send from" aria-describedby="account-number-from"
                       placeholder="Account number" name="senderAccountNumber" required>
            </div>
        </div>

        <p class="lead">Recipient account</p>

        <div class="dropdown account-dropdown mb-3" id="receiverDropdown">
            <button type="button" class="btn btn-light dropdown-toggle" id="receiverDropdownButton"
                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                Select Recipient account
            </button>
            <div class="dropdown-menu" aria-labelledby="receiverDropdownButton">
                <span class="dropdown-header">My accounts</span>
                <?php
                foreach ($accounts as $account) {
                    echo '<a class="dropdown-item" href="#" data-accountNumber="' . $account->accountNumber . '">' . $account->accountName . '</a>';
                }
                ?>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="#" data-otherAccount>Other account</a>
            </div>
        </div>

        <div class="form-group mb-3 d-none">
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text" id="account-number-to">Send to</span>
                </div>
                <input type="text" class="form-control" aria-label="Send to" aria-describedby="account-number-to"
                       placeholder="Account number" name="receiverAccountNumber" required>
            </div>
        </div>

        <label for="amount">
            <p class="lead">Amount to send</p>
        </label>

        <div class="form-group mb-3">
            <div class="input-group mb-3">
                <div class="input-group-prepend">
                    <span class="input-group-text">SGD</span>
                </div>
                <input type="text" class="form-control" aria-label="Amount" placeholder="Amount" id="amount"
                       name="amount" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary mb-3">Transfer</button>
    </form>

    <script>
        $('.account-dropdown a.dropdown-item').on('click', e => {
            const dropdown = $(e.target).parents('.dropdown')
            dropdown.children('button').text($(e.target).text())

            if (typeof ($(e.target).attr('data-otherAccount')) !== 'undefined') {
                // Other account
                dropdown.next().find('input').val('')
                dropdown.next().removeClass('d-none')

                $('a.dropdown-item').removeClass('disabled')
            } else {
                // One of own accounts
                dropdown.next().addClass('d-none')
                dropdown.next().find('input').val($(e.target).attr('data-accountNumber'))

                $('a.dropdown-item').removeClass('disabled')
                $('.account-dropdown').not(dropdown).find('a.dropdown-item[data-accountNumber="' + $(e.target).attr('data-accountNumber') + '"]').addClass('disabled')
            }
        })

        $('input[name=senderAccountNumber], input[name=receiverAccountNumber]').on('input', e => {
            const input = $(e.target)
            const dashIndices = [3, 9]

            for (let i of dashIndices) {
                if (input.val().length > i && input.val()[i] !== '-') {
                    input.val(input.val().slice(0, i) + '-' + input.val().slice(i))
                } else if (input.val().length === i + 1 && input.val()[i] === '-') {
                    input.val(input.val().slice(0, i))
                }
            }
        }).on('change', e => {
            const value = $(e.target).val().replaceAll('-', '')

            if (value.length !== 10) {
                e.target.setCustomValidity("Invalid account number")
                return
            }

            let accumulator = 0;
            for (let i = 0; i < 8; i++) {
                accumulator += (parseInt(value[i]) * (17 ** i)) % 17
            }

            if (value.slice(-2) !== (accumulator % 17).toString().padStart(2, '0')) {
                e.target.setCustomValidity("Invalid account number")
            } else {
                e.target.setCustomValidity("")
            }
        })
    </script>

</main>
<?php include '../include/footer.inc.php' ?>
</body>
</html>
