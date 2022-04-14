<?php
namespace App\Command;
use App\Model\LineGroup;

class Arithmetic  extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new Arithmetic();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '四則運算',
			'cmd' => '四則',
			'description' => '四則運算，用法: #四則{算式}',
			'args' => [
				'partner_id'
			],
      'access' => ['admin','group_admin'],
      'authorized_group_type' => ['Admin'],
		];
    $this->exp = [];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
		$group = $args->group;
		$user = $args->user;
		$command = $args->command;
		
		
		$message = "格式錯誤(E00),請使用以下格是:\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."{廠商ID}{類型(Booking/Admin/Server)}[{上(預設)/下}，例如：\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."2Booking";
			$this->command_data['pre_command'].$this->command_data['cmd']."2Booking下";
		
		if( strpos($command,$this->command_data['cmd'])!==0 )
			return $message;
		
		$command = substr($command, strlen($this->command_data['cmd']));
    $command = trim($command," ");
    $exp = $command;

    $arr_exp = array();



    for($i=0;$i<strlen($exp);$i++){

        $arr_exp[] = $exp[$i];

    }
    // print_r($arr_exp);exit;
    $result = $this->calcexp( array_reverse($arr_exp) );

    return $exp . '=' . $result;


    // 這個實現方式中使用瞭兩個堆棧，一個用來存儲數字，一個用來存儲運算符，遇到括號以後就遞歸進入括號內運算，實現方式有點笨拙，後面補充一下“逆波蘭表達式”的算法實現。

		
		// return $message;
	}
	protected function SessionFunction( $args=null ){
		
	}
  private function calcexp( $exp ){

    $arr_n = array();
    $arr_op = array();
    // $this->exp = $exp;

    while( ($s = $this->special_pop( $exp )) != '' ){

        if( $s == '(' ){

            $temp = array(); $quote = 1; $endquote = 0;

            while( ($t = $this->special_pop($exp)) != '' ){

                if( $t == '(' ){

                    $quote++;

                }

                if( $t == ')' ){

                    $endquote++;

                    if( $quote == $endquote ){

                        break;

                    }

                }

                array_push($temp, $t);

            }

            $temp = array_reverse($temp);

            array_push($arr_n, $this->calcexp($temp) );

        }else if( $s == '*' || $s == '/' ){

            $n2 = $this->special_pop($exp);

            if( $n2 == '(' ){

                $temp = array(); $quote = 1; $endquote = 0;

                while( ($t = $this->special_pop($exp)) != '' ){

                    if( $t == '(' ){

                        $quote++;

                    }

                    if( $t == ')' ){

                        $endquote++;

                        if( $quote == $endquote )

                            break;

                    }

                    array_push($temp, $t);

                }

                $temp = array_reverse($temp);

                $n2 = $this->calcexp($temp);

            }

            

            $op = $s;

            $n1 = array_pop($arr_n);

            

            $result = $this->operation($n1, $op, $n2);

            array_push($arr_n, $result);

        }elseif( $s == '+' || $s == '-' ){

            array_push($arr_op, $s);

        }else{

            array_push($arr_n, $s);

        }

    }

    

    $n2 = array_pop($arr_n);

    while( ($op = array_pop($arr_op)) != '' ){

        $n1 = array_pop($arr_n);

        $n2 = $this->operation($n1, $op, $n2);

    }

    

    return $n2;

}

private function special_pop(&$exp){
  if (!is_numeric(end($exp))){
    return array_pop($exp);
  }
  else{
    $temp_string  = '';
    while( is_numeric(end($exp)) ){
      $temp_string .= array_pop($exp);
    }
    return $temp_string;
  }
}


private function operation( $n1, $op, $n2 ){

    switch ($op) {

        case '+':

            return intval($n1)+intval($n2);

            break;

        case '-':

            return intval($n1)-intval($n2);

            break;

        case '*':

            return intval($n1)*intval($n2);

            break;

        case '/':

            return intval($n1)/intval($n2);

            break;

    }

}
}
