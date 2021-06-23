<?php
require 'gerencianet/gerencianet-sdk/autoload.php';
include_once 'gerencianet/gerencianet_lib/GerencianetValidation.php';
include_once 'gerencianet/gerencianet_lib/GerencianetIntegration.php';
include_once 'gerencianet/gerencianet_lib/functions/boleto/gateway_functions.php';
include_once 'gerencianet/gerencianet_lib/Gerencianet_WHMCS_Interface.php';

function gerencianet_config()
{
    $configarray = array(
        "FriendlyName"  => array(
            "Type"      => "System",
            "Value"     => "Gerencianet"
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

function gerencianet_link($params)
{
    $geraCharge = false;
    if (isset($_POST['geraCharge']))
        $geraCharge = $_POST['geraCharge'];

    /* **************************************** Verifica se a versão do PHP é compatível com a API ******************************** */

    if (version_compare(PHP_VERSION, '7.3') < 0) {
        $errorMsg = 'A versão do PHP do servidor onde o WHMCS está hospedado não é compatível com o módulo Gerencianet. Atualize o PHP para uma versão igual ou superior à versão 5.4.39';
        if ($params['configDebug'] == "on")
            logTransaction('gerencianet', $errorMsg, 'Erro de Versão');

        return send_errors(array('Erro Inesperado: Ocorreu um erro inesperado. Entre em contato com o responsável do WHMCS.'));
    }

    /* ***************************************************** Includes e captura do invoice **************************************** */

   
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
            logTransaction('gerencianet', 'O campo - Usuario administrador do WHMCS - está preenchido incorretamente', 'Erro de Integração');
        return send_errors($errorMessages);
    }

    /* ***************************** Verifica se já existe um boleto para o pedido em questão *********************************** */

    $gnIntegration = new GerencianetIntegration($clientIdProd, $clientSecretProd, $clientIdSandbox, $clientSecretSandbox, $configSandbox, $idConta);
    $existingChargeConfirm = existingCharge($params, $gnIntegration);
    $existingCharge = $existingChargeConfirm['existCharge'];
    $code = $existingChargeConfirm['code'];
    if ($existingCharge) {
        return $code;
    }

    $invoiceDescription         = $params['description'];
    $invoiceAmount              = $params['amount'];
    if ($invoiceAmount < $minValue) {
        $limitMsg = "<div id=limit-value-msg style='font-weight:bold; color:#cc0000;'>Transação Não permitida: Você está tentando pagar uma fatura de<br> R$ $invoiceAmount. Para gerar o boleto Gerencianet, o valor mínimo do pedido deve ser de R$ $minValue</div>";
        return $limitMsg;
    }

    if ($geraCharge == true) { 
        /* ************************************************* Invoice parameters *************************************************** */
        return createBillet($params, $gnIntegration, $errorMessages,$existingCharge);
    } else {
        return buttonGerencianet(null, null, $descontoBoleto, $tipoDesconto);
    }
}
