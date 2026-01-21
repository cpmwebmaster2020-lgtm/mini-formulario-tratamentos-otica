# Mini formulário de lentes – WooCommerce

Este projeto é uma versão organizada e reutilizável de um sistema em produção
usado em uma ótica para venda de lentes com prescrição médica.

## O que ele faz
- Permite escolher o tipo de lente no carrinho
- Soma o valor da lente ao total (fee do WooCommerce)
- Exige upload da receita (JPG, PNG ou PDF)
- Exige confirmação do cliente
- Bloqueia checkout sem receita válida
- Salva lente, valor e receita no pedido
- Mostra essas informações no admin do WooCommerce

## Shortcodes
Use no carrinho:
- `[cpm_escolha_lente]`
- `[cpm_upload_receita_carrinho]`

## Tecnologias
- WordPress
- WooCommerce
- PHP
- JavaScript (AJAX)
- Sessão WooCommerce

## Observação
Este repositório é destinado a estudo, reutilização e adaptação.
O código aqui é uma base funcional organizada a partir de um projeto real.
