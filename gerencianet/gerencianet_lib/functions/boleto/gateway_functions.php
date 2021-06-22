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

function validationParams($params){

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

function getClientVariables($params){

    $clientId         = $params['clientdetails']['id'];
    $document         = preg_replace("/[^0-9]/", "", get_custom_field_value((string)"CPF/CNPJ", $clientId));
    $corporateName    = $params['clientdetails']['companyname'];

    $name  = $params['clientdetails']['firstname'] . ' ' . $params['clientdetails']['lastname'];
    $phone = preg_replace('/[^0-9]/', '', $params['clientdetails']['phonenumber']);
    $email = $params['clientdetails']['email'];
    $sendEmailGN= $params['sendEmailGN'];

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

   


function createBillet($params){
    
}