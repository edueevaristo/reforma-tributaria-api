<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Excecao;

/**
 * A operacao existe na legislacao, mas ainda nao e calculada localmente por esta biblioteca.
 * Para esses casos, use a Calculadora de Tributos oficial (veja CalculadoraOficial\Cliente).
 */
class OperacaoNaoSuportadaException extends ReformaTributariaException
{
}
