<?php
namespace App\Command;

interface Command{
	public function getName() : string;
	public function execute( $args=null );
}