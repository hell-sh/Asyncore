<?php
namespace pas;
class AlwaysTrueCondition extends Condition
{
	function __construct()
	{
		parent::__construct(function(){});
	}

	function isTrue(): bool
	{
		return true;
	}
}
