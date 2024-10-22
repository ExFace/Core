<?php
interface Itf
{
    
}

class A implements Itf
{
    
}

class B implements Itf
{
    
}

class C extends A
{
    
}

class Test
{
    private $arr = [];

    public function addValidator(Itf $validator) : static
    {
        if(!in_array($validator, $this->arr)) {
            $this->arr[] = $validator;
        }

        return $this;
    }

    public function removeValidator(Itf $validator) : static
    {
        $index = array_search($validator, $this->arr);
        if($index !== false) {
            array_splice($this->arr, $index, 1);
        }
    
        return $this;
    }
}


$test = new Test();
$a = new A();
$test->addValidator($a);
$test->addValidator($a);
$b = new B();
$test->removeValidator($b);
$test->addValidator($b);
$c = new C();
$test->removeValidator($a);
$test->addValidator($c);
$test->addValidator($c);
$test->removeValidator($c);