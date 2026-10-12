# Consulta de CEP (PHP + API ViaCEP)

Aplicação web em PHP que recebe um CEP, consulta a API pública
[ViaCEP](https://viacep.com.br), interpreta o JSON retornado e exibe o endereço
dinamicamente.

## Estrutura

```
consulta-cep/
├── index.php   # página inicial: formulário + lógica PHP + área de resultados
├── style.css   # estilos
└── README.md
```

## Como funciona

1. O usuário digita o CEP e clica em **Consultar** (formulário `POST`).
2. O PHP remove tudo que não for dígito e valida se há 8 números.
3. O PHP faz `GET https://viacep.com.br/ws/{cep}/json/` (via cURL, com
   `file_get_contents` como alternativa).
4. O JSON recebido é decodificado com `json_decode()`.
5. Os campos são exibidos: CEP, Logradouro, Bairro, Cidade e Estado.

## Tratamento de erros

- Campo vazio ou CEP com quantidade de dígitos diferente de 8.
- CEP inexistente (a API responde `{"erro": true}`).
- Falha de conexão, timeout ou resposta inválida da API.
- Saída escapada com `htmlspecialchars()` (proteção contra XSS).

## Como executar

Requisitos: PHP 7.4 ou superior (recomendado 8.x) com acesso à internet.
A extensão cURL é opcional.

```bash
cd consulta-cep
php -S localhost:8000
```

Abra <http://localhost:8000> no navegador e teste com o CEP `01001000`.

Também funciona colocando a pasta em `htdocs` (XAMPP) ou `www` (WAMP).

## Exemplo de resultado

```
CEP: 01001-000
Logradouro: Praça da Sé
Complemento: lado ímpar
Bairro: Sé
Cidade: São Paulo
Estado: SP
```
