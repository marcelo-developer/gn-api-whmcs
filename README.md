# Módulo de Integração Gerencianet para WHMCS Oficial - Versão 0.2.0 #

O módulo Gerencianet para o WHMCS permite gerar boletos com registro através da nossa API.
Compatível com as versões superiores à 6.0.4 do WHMCS.

Este é uma versão Beta do Módulo Oficial de integração fornecido pela [Gerencianet](https://gerencianet.com.br/) para WHMCS. Com ele, o responsável pela conta WHMCS pode receber pagamentos por boleto bancário e, assim que a cobrança tem uma confirmação de pagamento ou é cancelada, a Gerencianet envia uma notificação automática para o WHMCS.

Caso você tenha alguma dúvida ou sugestão, entre em contato conosco pelo site [Gerencianet](https://gerencianet.com.br/).

## Instalação

1. Faça o download da última versão do módulo;
2. Descompacte o arquivo baixado;
3. Copie o arquivo gerencianetcharge.php e a pasta gerencianet_lib, disponíveis na pasta gn-api-whmcs, para o diretório /modules/gateways da instalação do WHMCS;
4. Copie o arquivo gerencianetcharge.php, disponível no diretório gn-api-whmcs/callback, para o diretório modules/gateways/callback. Ele deve seguir o modelo modules/gateways/callback/gerencianetcharge.php.

Os arquivos do módulo Gerencianet devem seguir a seguinte estrutura no WHMCS:

```
 modules/gateways/
  |- callback/gerencianetcharge.php
  |  gerencianet_lib/
  |  gerencianetcharge.php
```

## Configuração do Módulo

![Parametros de configuração do módulo Gerencianet](parametros_configuracao.png "Parametros de configuração do módulo Gerencianet")

Dentro do painel administrativo do WHMCS, acesse o menu "Setup" -> "Payments" -> "Payment Gateways". No campo "Active Module", escolha a opção Gerencianet. A tela mostrada acima será exibida em sua tela. Dentro do formulário exibido, você deverá preencher os seguintes campos:

1. **Client_Id Produção:** Deve ser preenchido com o client_id de produção de sua conta Gerencianet. Este campo é obrigatório e pode ser encontrado no menu "Nova API" -> "Minhas Aplicações" -> clique sobre sua aplicação do WHMCS -> Aba "Produção";
2. **Client_Secret Produção:** Deve ser preenchido com o client_secret de produção de sua conta Gerencianet. Este campo é obrigatório e pode ser encontrado no menu "Nova API" ->  "Minhas Aplicações" -> clique sobre sua aplicação do WHMCS -> Aba "Produção";
3. **Client_Id Desenvolvimento:** Deve ser preenchido com o client_id de desenvolvimento de sua conta Gerencianet. Este campo é obrigatório e pode ser encontrado no menu "Nova API" -> "Minhas Aplicações" -> clique sobre sua aplicação do WHMCS ->Aba "Desenvolvimento";
4. **Client_Secret Desenvolvimento:** Deve ser preenchido com o client_secret de desenvolvimento de sua conta Gerencianet. Este campo é obrigatório e pode ser encontrado no menu "Nova API" -> "Minhas Aplicações" -> clique sobre sua aplicação do WHMCS -> Aba "Desenvolvimento";
5. **Identificador da Conta:** Deve ser preenchido com o identificador de sua conta Gerencianet. Este campo é obrigatório e pode ser encontrado no menu "Nova API", na tela principal e no canto superior esquerdo;
6. **Usuario administrador do WHMCS:** Deve ser preenchido com o usuário administrador do WHMCS, sendo este usuário o mesmo que o administrador do WHMCS faz login na area administrativa de sua conta. Este campo é de preenchimento obrigatório; 
7. **Desconto do Boleto:** Informe o valor desconto que deverá ser aplicado aos boletos gerados exclusivamente pela Gerencianet. Esta informação é opcional;
8. **Tipo de desconto:** Informe o tipo de desconto (porcentagem ou valor fixo) que deverá ser aplicado aos boletos gerados exclusivamente pela Gerencianet. Esta informação é opcional; 
9. **Numero de dias para o vencimento da cobrança:** Informe o número de dias corridos para o vencimento do boleto Gerencianet após a cobrança ser gerada. Se o campo estiver vazio, o valor será 0;
10. **Nome do campo referente à CPF e/ou CNPJ:** Deve ser informado o nome do campo que o administrador do WHMCS criou para receber o CPF e/ou CNPJ do cliente final. Este campo é obrigatório, e caso você ainda não o tenha criado, vá ao painel administrativo do WHMCS em "Setup" -> "Custom Client Fields" e configure um campo para receber tais valores. Ex: "CPF/CNPJ". 
11. **Sandbox:** Caso seja de seu interesse, habilite o ambiente de testes da API Gerencianet;
12. **Debug:** Através desse campo é possível habilitar os logs de transação e de erros da Gerencianet no painel WHMCS;
13. **Email de cobrança - Gerencianet:** Caso seja de seu interesse, habilite o envio de emails de cobrança da Gerencianet para o cliente final;
14. **Intrução do boleto:** Configure as instruções do boleto que sejam de seu interesse;

#Erros de Integração:

Antes mesmo do módulo tentar gerar uma cobrança alguns campos requisitados na integração passam por uma validação. Os erros que esta validação pode retornar são:

1. **Nome Inválido:** O nome informado pelo cliente final é muito curto, assim, o mesmo deve digitar o nome completo;
2. **Email Inválido:** O email informado pelo cliente final é inválido (não segue o padrão xxxxx@xxxx.com) ou não existe;
3. **Telefone Inválido:** O telefone informado pelo cliente final não existe ou o DDD está incorreto;
4. **Documento Inválido:** O número do CPF/CNPJ do cliente final é invalido;
5. **Documento Nulo:** O campo referente ao CPF e/ou CNPJ do cliente não existe no WHMCS ou não está preenchido;
8. **Razão Social Inválida:** A Razão Social é inválida. O cliente deve digitar no campo "Empresa" do WHMCS o nome empresarial que consta na Receita Federal;
9. **Razão Social Nula:** O campo "Empresa" do WHMCS não está preenchido;
10. **Erro Inesperado:** Houve algum erro na integração. Provavelmente você não preencheu todos os campos do módulo corretamente, ou a versão do PHP do WHMCS não é compatível com a API Gerencianet. Você deverá ativar o modo Debug do módulo para saber mais detalhes.

Ainda que nenhum destes erros de validação sejam retornados, a API Gerencianet poderá retornar erros referentes à geração da cobrança. Para mais informações sobre os códigos de erros retornados pela API Gerencianet, [acesse](https://docs.gerencianet.com.br/codigos-de-erros).

##Descontos:

Neste módulo de integração é possível gerar boletos considerando os descontos dos cupons promocionais fornecidos pelo WHMCS.
Caso o integrador escolha uma das 4 formas de desconto do WHMCS (Porcentagem, valor fixo, Substituição de preço e isenção de tarifas), tal desconto é convertido em Reais e repassado à API Gerencianet no momento da geração do boleto.

Além dos descontos fornecidos pelo WHMCS, é possível disponibilizar descontos exclusivos para os boletos gerados através do módulo Gerencianet. Esta opção de desconto é configurada nos campos "Descoto do Boleto" e "Tipo de deconto" do módulo Gerencianet. Uma vez configurado, este disconto será exibido no boleto Gerencianet e, assim que o mesmo for pago, o valor do pedido e da cobrança no WHMCS serão atualizados para o valor com o desconto Gerencianet.

Outra forma de desconto além das citadas anteriormente são os créditos que o usuário tem no WHMCS. Assim, caso um cliente queira aplicar um determinado crédito no pedido do WHMCS, tal quantia será convertida em desconto no boleto Gerencianet. 

## Requisitos

* Versão mínima do PHP: 5.4.39
* Versão mínima do WHMCS: 6.0.4


