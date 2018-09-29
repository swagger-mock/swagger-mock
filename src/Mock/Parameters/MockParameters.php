<?php
/*
 * This file is part of Swagger Mock.
 *
 * (c) Igor Lazarev <strider2038@yandex.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Mock\Parameters;

/**
 * @author Igor Lazarev <strider2038@yandex.ru>
 */
class MockParameters
{
    /** @var string */
    public $httpMethod;

    /** @var string */
    public $path;

    /** @var MockResponseCollection */
    public $responses;
}