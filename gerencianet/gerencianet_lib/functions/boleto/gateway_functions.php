<?php

use Gerencianet\Gerencianet;

define("NAME_ERROR_MESSAGE", "Nome Inválido: O nome é muito curto. Você deve digitar seu nome completo.");
define("EMAIL_ERROR_MESSAGE", "Email Inválido: O email informado é inválido ou não existe.");
define("BIRTHDATE_ERROR_MESSAGE", "Data de nascimento Inválida: A data de nascimento informada deve seguir o padrão Ano-mes-dia.");
define("PHONENUMBER_ERROR_MESSAGE", "Telefone Inválido: O telefone informado não existe ou o DDD está incorreto.");
define("DOCUMENT_NULL_ERROR_MESSAGE", "Documento Nulo: O campo referente à CPF e/ou CNPJ não existe ou não está preenchido.");
define("CPF_ERROR_MESSAGE", "Documento Inválido: O número do CPF do cliente é invalido.");
define("CNPJ_ERROR_MESSAGE", "Documento Inválido: O número do CNPJ do cliente é invalido.");
define("CORPORATE_ERROR_MESSAGE", "Razão Social Inválida: O nome da empresa é inválido. Você deve digitar no campo \"Empresa\" de seu WHMCS o nome que consta na Receita Federal.");
define("CORPORATE_NULL_ERROR_MESSAGE", "Razao Social Nula: O campo \"Empresa\" de seu WHMCS não está preenchido.");
define("INTEGRATION_ERROR_MESSAGE", "Erro Inesperado: Ocorreu um erro inesperado. Entre em contato com o responsável do WHMCS.");

function validationParams($params)
{

    $validations   = new GerencianetValidation();
    $mensagensErros = array();

    /* ********************************************* Coleta os dados do cliente ************************************************* */
    $clientId         = $params['clientdetails']['id'];
    $document         = preg_replace("/[^0-9]/", "", get_custom_field_value((string)"CPF/CNPJ", $clientId));
    $corporateName    = $params['clientdetails']['companyname'];

    $name  = $params['clientdetails']['firstname'] . ' ' . $params['clientdetails']['lastname'];
    $phone = preg_replace('/[^0-9]/', '', $params['clientdetails']['phonenumber']);
    $email = $params['clientdetails']['email'];

    if ($document == null || $document == '')
        array_push($mensagensErros, DOCUMENT_NULL_ERROR_MESSAGE);
    else {
        if (strlen($document) <= 11) {
            $isJuridica = false;
            if (!$validations->_cpf($document))
                array_push($mensagensErros, CPF_ERROR_MESSAGE);
            if (!$validations->_name($name))
                array_push($mensagensErros, NAME_ERROR_MESSAGE);
        } else {
            $isJuridica = true;
            if (!$validations->_cnpj($document))
                array_push($mensagensErros, CNPJ_ERROR_MESSAGE);
            if ($corporateName == null || $corporateName == '')
                array_push($mensagensErros, CORPORATE_NULL_ERROR_MESSAGE);
            elseif (!$validations->_corporate($corporateName))
                array_push($mensagensErros, CORPORATE_ERROR_MESSAGE);
        }
    }

    if (!$validations->_phone_number($phone))
        array_push($mensagensErros, PHONENUMBER_ERROR_MESSAGE);

    if (!$validations->_email($email))
        array_push($mensagensErros, EMAIL_ERROR_MESSAGE);


    return $mensagensErros;
}

function getClientVariables($params)
{

    $clientId         = $params['clientdetails']['id'];
    $document         = preg_replace("/[^0-9]/", "", get_custom_field_value((string)"CPF/CNPJ", $clientId));
    $corporateName    = $params['clientdetails']['companyname'];

    $name  = $params['clientdetails']['firstname'] . ' ' . $params['clientdetails']['lastname'];
    $phone = preg_replace('/[^0-9]/', '', $params['clientdetails']['phonenumber']);
    $email = $params['clientdetails']['email'];
    $sendEmailGN = $params['sendEmailGN'];

    if (strlen($document) <= 11)
        $isJuridica = false;

    if ($isJuridica == false) {
        if ($sendEmailGN == "on")
            $customer = array(
                'name'          => $name,
                'cpf'           => (string)$document,
                'email'         => $email,
                'phone_number'  => $phone
            );
        else
            $customer = array(
                'name'          => $name,
                'cpf'           => (string)$document,
                'phone_number'  => $phone
            );
    } else {
        $juridical_data = array(
            'corporate_name' => (string)$corporateName,
            'cnpj'           => (string)$document
        );

        if ($sendEmailGN == "on")
            $customer = array(
                'email'             => $email,
                'phone_number'      => $phone,
                'juridical_person'  => $juridical_data
            );
        else
            $customer = array(
                'phone_number'      => $phone,
                'juridical_person'  => $juridical_data
            );
    }


    return $customer;
}

function existingCharge($params, $gnIntegration)
{
    $getTransactionValues['invoiceid']  = $params['invoiceid'];
    $transactionData                    = localAPI("gettransactions", $getTransactionValues, $params['whmcsAdmin']);
    $totalTransactions                  = $transactionData['totalresults'];
    $existCharge                        = false;
    $resultado =  array();
    $resultado['existCharge'] = $existCharge;

    if ($totalTransactions > 0) {
        $lastTransaction   = end($transactionData['transactions']['transaction']);
        $transactionId     = (string)$lastTransaction['id'];
        $chargeId          = (int)$lastTransaction['transid'];
        $chargeDetailsJson = $gnIntegration->detail_charge($chargeId);
        $chargeDetails     = json_decode($chargeDetailsJson, true);

        if ($chargeDetails['code'] == 200) {
            $existCharge = true;
            $resultado['existCharge'] = $existCharge;
            if (isset($chargeDetails['data']['payment']['banking_billet']['pdf']['charge'])) {
                $url  = $chargeDetails['data']['payment']['banking_billet']['pdf']['charge'];
                $code = buttonGerencianet(null, $url);
                $resultado['code'] = $code;
                return $resultado;
            }
        } else {
            return $resultado;
        }
    }
}


function createBillet($params, $gnIntegration, $errorMessages,$existCharge)
{

    $invoiceValues['invoiceid'] = $params['invoiceid'];
    $adminWHMCS = $params['whmcsAdmin'];
    $invoiceData                = localAPI("getinvoice", $invoiceValues, $adminWHMCS);
    $numDiasParaVencimento  = $params['numDiasParaVencimento'];
    $descontoBoleto = $params['descontoBoleto'];
    $tipoDesconto           = $params['tipoDesconto'];
    $fineValue              = $params['fineValue'];
    $interestValue          = $params['interestValue'];
    $invoiceId = $params['invoiceId'];
    $configDebug            = $params['configDebug'];

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
            $gnApiResult = $gnIntegration->create_charge($items, $invoiceId, $params['systemurl'] . 'modules/gateways/callback/gerencianet.php');
            $resultCheck = json_decode($gnApiResult, true);
            if ($resultCheck['code'] != 0) {
                $chargeId                               = $resultCheck['data']['charge_id'];
                $addTransactionCommand                  = "addtransaction";
                $addTransactionValues['userid']         = $userId;
                $addTransactionValues['invoiceid']      = $invoiceId;
                $addTransactionValues['description']    = "Boleto Gerencianet: Cobrança gerada.";
                $addTransactionValues['amountin']       = '0.00';
                $addTransactionValues['fees']           = '0.00';
                $addTransactionValues['paymentmethod']  = 'gerencianet';
                $addTransactionValues['transid']        = (string)$chargeId;
                $addTransactionValues['date']           = date('d/m/Y');
                $addtransresults = localAPI($addTransactionCommand, $addTransactionValues, $adminWHMCS);
                $permitionToPay = true;
            } else
                $permitionToPay = false;
        }

        if ($permitionToPay == true) {
            $resultPayment = $gnIntegration->pay_billet($chargeId, $newDueDate, $customer, $params['message'], $fineValue, $interestValue, $discount);
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
                    logTransaction('gerencianet', array('invoiceid' => $invoiceId, 'charge_id' => $chargeId, 'error' => $resultPaymentDecoded['messageAdmin']), 'Erro Gerencianet: Geração do boleto');
                return send_errors($errorMessages);
            }
        } else {
            array_push($errorMessages, $resultCheck['message']);
            if ($configDebug == "on")
                logTransaction('gerencianet', array('invoiceid' => $invoiceId, 'error' => $resultCheck['messageAdmin']), 'Erro Gerencianet: Geração da cobrança');
            return send_errors($errorMessages);
        }
    } else {
        $validationErrors = array('invoiceid' => $invoiceId);

        foreach ($errorMessages as $error) {
            array_push($validationErrors, $error);
        }

        if ($configDebug == "on")
            logTransaction('gerencianet', $validationErrors, 'Erro Gerencianet: Validação');
        return send_errors($errorMessages);
    }
}
