<?php
declare(strict_types=1);
require __DIR__.'/harness.php';
use Hostider\Finance\{Currency,Decimal,Money,Rounding,Unsigned};

test('F2-A exact arithmetic exceeds JavaScript and machine integer precision',function(): void {
    same('9007199254740994',(string)(new Decimal('9007199254740993'))->add(new Decimal('1')));
    same('9999999999999999999800000000000000000001',Unsigned::multiply('99999999999999999999','99999999999999999999'));
    same(['33333333333333333333','0'],Unsigned::divide('99999999999999999999','3'));
    same('-0.02',(string)(new Decimal('0.01'))->subtract(new Decimal('0.03')));
    same('0',(string)(new Decimal('-0.000')));
});
test('F2-A explicit symmetric rounding and ties to even',function(): void {
    foreach (['1.005'=>'1.01','-1.005'=>'-1.01','0.004'=>'0.00','-0.004'=>'0.00'] as $input=>$expected) {
        same($expected,(new Decimal($input))->quantize(2,Rounding::HalfUp));
    }
    same('1.00',(new Decimal('1.005'))->quantize(2,Rounding::HalfEven));
    same('1.02',(new Decimal('1.015'))->quantize(2,Rounding::HalfEven));
    same('-1.00',(new Decimal('-1.009'))->quantize(2,Rounding::TowardZero));
    same('0.333333333333',(string)(new Decimal('1'))->divide(new Decimal('3'),12,Rounding::HalfUp));
    same('131.58',(string)(new Decimal('3'))->multiply(new Decimal('43'))->multiply(new Decimal('1.02')));
});
test('F2-A money minor strings survive JSON and currency precision',function(): void {
    $m=new Money('9007199254740993',new Currency('TRY'));
    $json=json_decode(json_encode($m,JSON_THROW_ON_ERROR),true,32,JSON_THROW_ON_ERROR);
    same('9007199254740993',$json['minor']); same('90071992547409.93',(string)$m->decimal());
    same('1',Money::fromDecimal(new Decimal('0.005'),new Currency('TRY'),Rounding::HalfUp)->minor);
    same('-1',Money::fromDecimal(new Decimal('-0.005'),new Currency('TRY'),Rounding::HalfUp)->minor);
    same('124',Money::fromDecimal(new Decimal('123.5'),new Currency('JPY'),Rounding::HalfEven)->minor);
    same('1235',Money::fromDecimal(new Decimal('1.2345'),new Currency('KWD'),Rounding::HalfUp)->minor);
});
test('F2-A malformed or lossy input and overflow fail closed',function(): void {
    foreach (['1e3','NaN','INF',' 1','1.','+1','1,23'] as $value) { raises(InvalidArgumentException::class,fn()=>new Decimal($value)); }
    foreach (['01','-0','1.00','1e2'] as $value) { raises(InvalidArgumentException::class,fn()=>new Money($value,new Currency('TRY'))); }
    raises(OverflowException::class,fn()=>new Money('9223372036854775808',new Currency('TRY')));
    raises(OverflowException::class,fn()=>(new Money('9223372036854775807',new Currency('TRY')))->add(new Money('1',new Currency('TRY'))));
    raises(InvalidArgumentException::class,fn()=>new Currency('BAD'));
    raises(InvalidArgumentException::class,fn()=>(new Money('1',new Currency('TRY')))->add(new Money('1',new Currency('USD'))));
    raises(DivisionByZeroError::class,fn()=>(new Decimal('1'))->divide(new Decimal('0'),2,Rounding::HalfUp));
});
runTests();
