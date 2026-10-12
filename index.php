<?php
declare(strict_types=1);

/**
 * Consulta de CEP - consome a API pública ViaCEP (https://viacep.com.br)
 * Fluxo: formulário -> PHP faz GET na API -> recebe JSON -> interpreta -> exibe.
 */

/** Escapa texto para exibição segura em HTML. */
function e(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/** Formata CEP no padrão 00000-000. */
function formatarCep(string $cep): string
{
    $cep = preg_replace('/\D/', '', $cep) ?? '';
    return strlen($cep) === 8 ? substr($cep, 0, 5) . '-' . substr($cep, 5) : $cep;
}

/**
 * Faz a requisição HTTP GET à API e devolve o JSON decodificado.
 *
 * @return array{dados: ?array, erro: ?string}
 */
function consultarViaCep(string $cep): array
{
    $url = "https://viacep.com.br/ws/{$cep}/json/";

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $resposta = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $falha    = curl_error($ch);
        curl_close($ch);
    } else {
        // Alternativa caso a extensão cURL não esteja habilitada
        $contexto = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
        $resposta = @file_get_contents($url, false, $contexto);
        $status   = 200;
        $falha    = $resposta === false ? 'Falha na conexão.' : '';
    }

    if ($resposta === false || $falha !== '') {
        return ['dados' => null, 'erro' => 'Não foi possível conectar à API. Tente novamente em instantes.'];
    }
    if ($status === 400) {
        return ['dados' => null, 'erro' => 'CEP em formato inválido.'];
    }
    if ($status !== 200) {
        return ['dados' => null, 'erro' => "A API retornou um erro (HTTP {$status})."];
    }

    $dados = json_decode($resposta, true);
    if (!is_array($dados)) {
        return ['dados' => null, 'erro' => 'Resposta inválida recebida da API.'];
    }
    // A ViaCEP responde {"erro": true} quando o CEP tem formato válido mas não existe
    if (!empty($dados['erro'])) {
        return ['dados' => null, 'erro' => 'CEP não encontrado.'];
    }

    return ['dados' => $dados, 'erro' => null];
}

$cepDigitado = '';
$resultado   = null;
$mensagem    = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cepDigitado = trim((string) ($_POST['cep'] ?? ''));
    $cepLimpo    = preg_replace('/\D/', '', $cepDigitado) ?? '';

    if ($cepLimpo === '') {
        $mensagem = 'Informe um CEP para consultar.';
    } elseif (strlen($cepLimpo) !== 8) {
        $mensagem = 'O CEP deve conter exatamente 8 dígitos.';
    } else {
        $retorno = consultarViaCep($cepLimpo);
        $resultado = $retorno['dados'];
        $mensagem  = $retorno['erro'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Consulta de CEP</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="container">
    <h1>Consulta de CEP</h1>
    <p class="subtitulo">Digite um CEP para buscar o endereço na API pública ViaCEP.</p>

    <form method="post" action="" class="formulario">
        <label for="cep">CEP</label>
        <div class="linha">
            <input
                type="text"
                id="cep"
                name="cep"
                placeholder="Ex.: 01001000 ou 01001-000"
                value="<?= e($cepDigitado) ?>"
                inputmode="numeric"
                maxlength="9"
                autocomplete="off"
                required
                autofocus
            >
            <button type="submit">Consultar</button>
        </div>
    </form>

    <section class="resultado" aria-live="polite">
        <h2>Resultado</h2>

        <?php if ($mensagem !== null): ?>
            <p class="alerta"><?= e($mensagem) ?></p>

        <?php elseif ($resultado !== null): ?>
            <dl>
                <dt>CEP</dt>        <dd><?= e(formatarCep($resultado['cep'] ?? '')) ?></dd>
                <dt>Logradouro</dt> <dd><?= e($resultado['logradouro'] ?? '') ?: '—' ?></dd>
                <?php if (!empty($resultado['complemento'])): ?>
                    <dt>Complemento</dt> <dd><?= e($resultado['complemento']) ?></dd>
                <?php endif; ?>
                <dt>Bairro</dt>     <dd><?= e($resultado['bairro'] ?? '') ?: '—' ?></dd>
                <dt>Cidade</dt>     <dd><?= e($resultado['localidade'] ?? '') ?></dd>
                <dt>Estado</dt>     <dd><?= e($resultado['uf'] ?? '') ?></dd>
            </dl>

        <?php else: ?>
            <p class="vazio">Nenhuma consulta realizada ainda.</p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
