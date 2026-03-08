<?php

uses(Tests\TestCase::class)->in('Feature', 'Unit', 'Contract', 'Integration');
uses()->group('arch')->in('Arch');
uses()->group('unit')->in('Unit');
uses()->group('feature')->in('Feature');
uses()->group('contract')->in('Contract');
uses()->group('integration')->in('Integration');
