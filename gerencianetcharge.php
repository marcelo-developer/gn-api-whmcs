<?php
require 'gerencianet/gerencianet-sdk/autoload.php';
include_once 'gerencianet/gerencianet_lib/GerencianetValidation.php';
include_once 'gerencianet/gerencianet_lib/GerencianetIntegration.php';
include_once 'gerencianet/gerencianet_lib/functions/boleto/gateway_functions.php';
include_once 'gerencianet/gerencianet_lib/Gerencianet_WHMCS_Interface.php';

function gerencianetcharge_config()
{
    $configarray = array(
        "FriendlyName"  => array(
            "Type"      => "System",
            "Value"     => "Gerencianet via Boleto"
        ),

        "clientIdProd"      => array(
            "FriendlyName"  => "Client_Id Produção (*)",
            "Type"          => "text",
            "Size"          => "50",
            "Description"   => " (preenchimento obrigatório)",
        ),

        "clientSecretProd"  => array(
            "FriendlyName"  => "Client_Secret Produção (*)",
            "Type"          => "text",
            "Size"          => "54",
            "Description"   => " (preenchimento obrigatório)",
        ),

        "clientIdSandbox"       => array(
            "FriendlyName"  => "Client_Id Desenvolvimento (*)",
            "Type"          => "text",
            "Size"          => "50",
            "Description"   => " (preenchimento obrigatório)",
        ),

        "clientSecretSandbox"   => array(
            "FriendlyName"  => "Client_Secret Desenvolvimento (*)",
            "Type"          => "text",
            "Size"          => "54",
            "Description"   => " (preenchimento obrigatório)",
        ),

        "idConta"           => array(
            "FriendlyName"  => "Identificador da Conta (*)",
            "Type"          => "text",
            "Size"          => "32",
            "Description"   => " (preenchimento obrigatório)",
        ),

        "whmcsAdmin"    => array(
            "FriendlyName"  => "Usuario administrador do WHMCS (*)",
            "Type"          => "text",
            "Description"   => "Insira o nome do usuário administrador do WHMCS.",
            "Description"   => " (preenchimento obrigatório)",
        ),

        "descontoBoleto"    => array(
            "FriendlyName"  => "Desconto do Boleto",
            "Type"          => "text",
            "Description"   => "Desconto para pagamentos no boleto bancário.",
        ),

        "tipoDesconto"      => array(
            'FriendlyName'  => 'Tipo de desconto',
            'Type'          => 'dropdown',
            'Options'       => array(
                '1'         => '% (Porcentagem)',
                '2'         => 'R$ (Reais)',
            ),
            'Description'   => 'Escolha a forma do desconto: Porcentagem ou em Reais',
        ),

        "numDiasParaVencimento" => array(
            "FriendlyName"      => "Número de dias para o vencimento da cobrança",
            "Type"              => "text",
            "Description"       => "Número de dias corridos para o vencimento da cobrança depois que a mesma foi gerada",
        ),

        "documentField" => array(
            "FriendlyName"      => "Nome do campo referente à CPF e/ou CNPJ (*)",
            "Type"              => "text",
            "Description"       => "Informe o nome do campo referente à CPF e/ou CNPJ no seu WHMCS. (preenchimento obrigatório)",
        ),

        "configSandbox"     => array(
            "FriendlyName"  => "Sandbox",
            "Type"          => "yesno",
            "Description"   => "Habilita o ambiente de testes da API Gerencianet",
        ),

        "configDebug"       => array(
            "FriendlyName"  => "Debug",
            "Type"          => "yesno",
            "Description"   => "Habilita logs de transação e de erros referentes à integração da API Gerencianet com o WHMCS",
        ),

        "sendEmailGN"       => array(
            "FriendlyName"  => "Email de cobraça - Gerencianet",
            "Type"          => "yesno",
            "Description"   => "Marque esta opção se você deseja que a Gerencianet envie emails de transações para o cliente final",
        ),

        "fineValue"         => array(
            "FriendlyName"  => "Configuração de Multa",
            'Type'          => 'text',
            "Description"   => "Valor da multa se pago após o vencimento - informe em porcentagem (mínimo 0,01% e máximo 10%).",
        ),

        "interestValue"         => array(
            "FriendlyName"  => "Configuração de Juros",
            'Type'          => 'text',
            "Description"   => "Valor de juros por dia se pago após o vencimento - informe em porcentagem (mínimo 0,001% e máximo 0,33%).",
        ),

        "message"      => array(
            'FriendlyName'  => 'Observação',
            'Type'          => 'text',
            'Size'          => '80',
            'Description'   => 'Permite incluir no boleto uma mensagem para o cliente (máximo de 80 caracteres).',
        ),

    );
    return $configarray;
}

function gerencianetcharge_link($params)
{
    $geraCharge = false;
    if (isset($_POST['geraCharge']))
        $geraCharge = $_POST['geraCharge'];

    /* **************************************** Verifica se a versão do PHP é compatível com a API ******************************** */

    if (version_compare(PHP_VERSION, '7.3') < 0) {
        $errorMsg = 'A versão do PHP do servidor onde o WHMCS está hospedado não é compatível com o módulo Gerencianet. Atualize o PHP para uma versão igual ou superior à versão 5.4.39';
        if ($params['configDebug'] == "on")
            logTransaction('gerencianetcharge', $errorMsg, 'Erro de Versão');

        return send_errors(array('Erro Inesperado: Ocorreu um erro inesperado. Entre em contato com o responsável do WHMCS.'));
    }

    /* ***************************************************** Includes e captura do invoice **************************************** */

    $invoiceId   = $params['invoiceid'];
    $urlCallback = $params['systemurl'] . 'modules/gateways/callback/gerencianetcharge.php';

    /* ************************************************ Define mensagens de erro ***************************************************/


    $errorMessages = array();
    $errorMessages = validationParams($params);


    /* ******************************************** Gateway Configuration Parameters ******************************************* */

    $clientIdProd           = $params['clientIdProd'];
    $clientSecretProd       = $params['clientSecretProd'];
    $clientIdSandbox            = $params['clientIdSandbox'];
    $clientSecretSandbox        = $params['clientSecretSandbox'];
    $idConta                = $params['idConta'];
    $descontoBoleto         = $params['descontoBoleto'];
    $tipoDesconto           = $params['tipoDesconto'];
    $numDiasParaVencimento  = $params['numDiasParaVencimento'];
    $documentField          = $params['documentField'];
    $minValue               = 5; //Valor mínimo de emissão de boleto na Gerencianet
    $configSandbox          = $params['configSandbox'];
    $configDebug            = $params['configDebug'];
    $configVencimento       = $params['configVencimento'];
    $sendEmailGN            = $params['sendEmailGN'];
    $fineValue              = $params['fineValue'];
    $interestValue          = $params['interestValue'];
    $billetMessage          = $params['message'];
    $adminWHMCS             = $params['whmcsAdmin'];


    if ($adminWHMCS == '' || $adminWHMCS == null) {
        array_push($errorMessages, INTEGRATION_ERROR_MESSAGE);
        if ($configDebug == "on")
            logTransaction('gerencianetcharge', 'O campo - Usuario administrador do WHMCS - está preenchido incorretamente', 'Erro de Integração');
        return send_errors($errorMessages);
    }

    /* ***************************** Verifica se já existe um boleto para o pedido em questão *********************************** */

    $gnIntegration = new GerencianetIntegration($clientIdProd, $clientSecretProd, $clientIdSandbox, $clientSecretSandbox, $configSandbox, $idConta);

    $getTransactionValues['invoiceid']  = $invoiceId;
    $transactionData                    = localAPI("gettransactions", $getTransactionValues, $adminWHMCS);
    $totalTransactions                  = $transactionData['totalresults'];
    $existCharge                        = false;

    if ($totalTransactions > 0) {
        $lastTransaction   = end($transactionData['transactions']['transaction']);
        $transactionId     = (string)$lastTransaction['id'];
        $chargeId          = (int)$lastTransaction['transid'];
        $chargeDetailsJson = $gnIntegration->detail_charge($chargeId);
        $chargeDetails     = json_decode($chargeDetailsJson, true);

        if ($chargeDetails['code'] == 200) {
            $existCharge = true;
            if (isset($chargeDetails['data']['payment']['banking_billet']['pdf']['charge'])) {
                $url  = $chargeDetails['data']['payment']['banking_billet']['pdf']['charge'];
                $code = buttonGerencianet(null, $url);
                return $code;
            }
        }
    }

    $invoiceDescription         = $params['description'];
    $invoiceAmount              = $params['amount'];
    if ($invoiceAmount < $minValue) {
        $limitMsg = "<div id=limit-value-msg style='font-weight:bold; color:#cc0000;'>Transação Não permitida: Você está tentando pagar uma fatura de<br> R$ $invoiceAmount. Para gerar o boleto Gerencianet, o valor mínimo do pedido deve ser de R$ $minValue</div>";
        return $limitMsg;
    }

    if ($geraCharge == true) {
        /* ************************************************* Invoice parameters *************************************************** */

        $invoiceValues['invoiceid'] = $invoiceId;
        $invoiceData                = localAPI("getinvoice", $invoiceValues, $adminWHMCS);

        /* ***************************************** Calcula data de vencimento do boleto **************************************** */

        if ($numDiasParaVencimento == null || $numDiasParaVencimento == '')
            $numDiasParaVencimento = '0';

        $userId  = $invoiceData['userid'];
        $duedate = $invoiceData['duedate'];

        if ($duedate < date('Y-m-d'))
            $duedate = date('Y-m-d');

        $date = DateTime::createFromFormat('Y-m-d', $duedate);
        $date->add(new DateInterval('P' . (string)$numDiasParaVencimento . 'D'));
        $newDueDate = (string)$date->format('Y-m-d');


        /* *********************************************** Coleta dados dos itens ************************************************* */

        $valueDiscountWHMCS      = 0;
        $percentageDiscountWHMCS = 0;
        $invoiceItems = $invoiceData['items']['item'];
        $invoiceTax   = (float)$invoiceData['tax'];
        $invoiceTax2  = (float)$invoiceData['tax2'];

        $totalItem     = 0;
        $totalTaxes    = 0;
        $items = array();

        foreach ($invoiceItems as $invoiceItem) {
            if ((float)$invoiceItem['amount'] > 0) {
                $itemValue = number_format($invoiceItem['amount'], 2, '.', '');
                $itemValue = preg_replace("/[.,-]/", "", $itemValue);
                $item = array(
                    'name'   => str_replace("'", "", $invoiceItem['description']),
                    'amount' => 1,
                    'value'  => (int)$itemValue
                );
                array_push($items, $item);
                $totalItem += (float)$invoiceItem['amount'];
            } else {
                $valueDiscountWHMCS += (float)$invoiceItem['amount'];
            }
        }

        if ($invoiceTax > 0) {
            $totalTaxes += (float)$invoiceTax;
            $item = array(
                'name'   => 'Taxa 1: Taxa adicional do WHMCS',
                'amount' => 1,
                'value'  => (int)($invoiceTax * 100)
            );
            array_push($items, $item);
        }

        if ($invoiceTax2 > 0) {
            $totalTaxes += (float)$invoiceTax2;
            $item = array(
                'name'   => 'Taxa 2: Taxa adicional do WHMCS',
                'amount' => 1,
                'value'  => (int)($invoiceTax2 * 100)
            );
            array_push($items, $item);
        }

        $valueDiscountWHMCSFormated = number_format($valueDiscountWHMCS, 2, '.', '');
        $valueDiscountWHMCSinCents  = preg_replace("/[.,-]/", "", $valueDiscountWHMCSFormated);
        $percentageDiscountWHMCS    = ((100 * $valueDiscountWHMCS) / $totalItem);
        $percentageDiscountWHMCS    = number_format($percentageDiscountWHMCS, 2, '.', '');
        $percentageDiscountWHMCS    = preg_replace("/[.,-]/", "", $percentageDiscountWHMCS);

        /* ***************************************** Calcula desconto do boleto e do WHMCS ******************************************* */

        $discount = false;
        $discountWHMCS = 0;
        $invoiceCredit = (int)(number_format((float)$invoiceData['credit'], 2, '.', '') * 100);

        $descontoBoleto = number_format((float)$descontoBoleto, 2, '.', '');

        if ($tipoDesconto == '1')
            $discounGerencianet         = ((float)$descontoBoleto / 100) * $totalItem;
        else
            $discounGerencianet  = (float)$descontoBoleto;

        $discounGerencianetFormated = number_format($discounGerencianet, 2, '.', '');
        $discounGerencianetinCents  = (float)$discounGerencianetFormated * 100;
        $discountValue = $discounGerencianetinCents + $valueDiscountWHMCSinCents + $invoiceCredit;

        $discountInReals = (float)$discounGerencianetFormated + (float)$valueDiscountWHMCSFormated + (float)$invoiceData['credit'];

        if ($discountValue > 0)
            $discount = array(
                'type' => 'currency',
                'value' => (int)$discountValue
            );

        $invoiceTotalInReals = (float)$totalItem - $discountInReals + $totalTaxes;

        

        /* *********************************************** Multa, juros e observação no boleto ******************************************** */

        $fineValue      = preg_replace("/[,]/", ".", $fineValue);
        $fineValue      = preg_replace("/[%]/", "", $fineValue);
        $fineValue      = number_format($fineValue, 2, '.', '');
        $fineValue      = (int)preg_replace("/[.,-]/", "", $fineValue);

        $interestValue  = preg_replace("/[,]/", ".", $interestValue);
        $interestValue  = preg_replace("/[%]/", "", $interestValue);
        $interestValue  = number_format($interestValue, 3, '.', '');
        $interestValue  = (int)preg_replace("/[.,-]/", "", $interestValue);

        /* ******************************************************* Gera a charge e o boleto ************************************************ */
        
        if (empty($errorMessages)) {
            $customer = getClientVariables($params);
            $permitionToPay = true;
            $resultCheck = array();

            if ($existCharge == false) {
                $gnApiResult = $gnIntegration->create_charge($items, $invoiceId, $urlCallback);
                $resultCheck = json_decode($gnApiResult, true);
                if ($resultCheck['code'] != 0) {
                    $chargeId                               = $resultCheck['data']['charge_id'];
                    $addTransactionCommand                  = "addtransaction";
                    $addTransactionValues['userid']         = $userId;
                    $addTransactionValues['invoiceid']      = $invoiceId;
                    $addTransactionValues['description']    = "Boleto Gerencianet: Cobrança gerada.";
                    $addTransactionValues['amountin']       = '0.00';
                    $addTransactionValues['fees']           = '0.00';
                    $addTransactionValues['paymentmethod']  = 'gerencianetcharge';
                    $addTransactionValues['transid']        = (string)$chargeId;
                    $addTransactionValues['date']           = date('d/m/Y');
                    $addtransresults = localAPI($addTransactionCommand, $addTransactionValues, $adminWHMCS);
                    $permitionToPay = true;
                } else
                    $permitionToPay = false;
            }

            if ($permitionToPay == true) {
                $resultPayment = $gnIntegration->pay_billet($chargeId, $newDueDate, $customer, $billetMessage, $fineValue, $interestValue, $discount);
                $resultPaymentDecoded = json_decode($resultPayment, true);

                if ($resultPaymentDecoded['code'] != 0) {
                    $getTransactionValues['invoiceid']  = $invoiceId;
                    $transactionData                    = localAPI("gettransactions", $getTransactionValues, $adminWHMCS);
                    $lastTransaction                    = end($transactionData['transactions']['transaction']);
                    $transactionId                      = (string)$lastTransaction['id'];

                    $data      = $resultPaymentDecoded['data'];
                    $chargeId  = $data["charge_id"];
                    $url       = (string)$data['pdf']['charge'];

                    $updateTransactionCommand                  = "updatetransaction";
                    $updateTransactionValues['transactionid']  = $transactionId;
                    $updateTransactionValues['description']    = "Boleto Gerencianet: Cobrança aguardando pagamento.";
                    $updatetransresults = localAPI($updateTransactionCommand, $updateTransactionValues, $adminWHMCS);
                    $code = "<meta http-equiv='refresh' content='0;url=" . $resultPaymentDecoded["data"]['pdf']['charge'] . "'>";
                    return $code;
                } else {
                    array_push($errorMessages, $resultPaymentDecoded['message']);
                    if ($configDebug == "on")
                        logTransaction('gerencianetcharge', array('invoiceid' => $invoiceId, 'charge_id' => $chargeId, 'error' => $resultPaymentDecoded['messageAdmin']), 'Erro Gerencianet: Geração do boleto');
                    return send_errors($errorMessages);
                }
            } else {
                array_push($errorMessages, $resultCheck['message']);
                if ($configDebug == "on")
                    logTransaction('gerencianetcharge', array('invoiceid' => $invoiceId, 'error' => $resultCheck['messageAdmin']), 'Erro Gerencianet: Geração da cobrança');
                return send_errors($errorMessages);
            }
        } else {
            $validationErrors = array('invoiceid' => $invoiceId);

            foreach ($errorMessages as $error) {
                array_push($validationErrors, $error);
            }

            if ($configDebug == "on")
                logTransaction('gerencianetcharge', $validationErrors, 'Erro Gerencianet: Validação');
            return send_errors($errorMessages);
        }
    } else {
        return buttonGerencianet(null, null, $descontoBoleto, $tipoDesconto);
    }
}
