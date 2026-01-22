Mini formulário para óticas com seleção de tratamentos de lentes (ex: multifocal, antirreflexo, blue light) e upload de receita. Estrutura flexível em HTML, CSS, JS e PHP, pensada para adaptação conforme cada ótica e integração com WooCommerce.

📌 Como funciona este mini formulário

Este projeto não é um plugin fechado, e sim um mini formulário reutilizável para óticas, desenvolvido com PHP e JavaScript, seguindo o padrão do WordPress.

🧩 Estrutura do código

PHP

Registra os shortcodes

Gera todo o HTML do formulário diretamente

Integra com WooCommerce quando necessário

JavaScript

Controla o comportamento do formulário

Valida ações do usuário (ex: envio de receita)

Pode bloquear a finalização da compra, se configurado

📌 Não existe um arquivo HTML separado, pois o HTML é gerado dinamicamente dentro do PHP — que é a prática padrão no WordPress.

🧠 Por que não há lista automática de lentes?

Este mini formulário não puxa dados de banco, produtos ou atributos por padrão.

Isso é intencional.

Cada ótica trabalha com:

tratamentos diferentes

preços diferentes

nomes comerciais diferentes

Por isso, os tratamentos de lentes devem ser definidos manualmente no código, permitindo total liberdade de personalização.

Exemplos de tratamentos:

Multifocal

Visão simples

Antirreflexo

Blue Light

Fotocromático

🔧 Como usar

Copie os arquivos PHP e JS do repositório

Ajuste os tratamentos de lentes conforme a necessidade da ótica

Insira o shortcode na página desejada (ex: carrinho ou página personalizada)

Estilize conforme o layout do site

🎯 Objetivo do projeto

Este repositório serve como:

Base técnica

Snippet reutilizável

Ponto de partida para projetos de ótica

Não é um plugin pronto para uso genérico, e sim um modelo flexível, pensado para desenvolvedores e web designers.
