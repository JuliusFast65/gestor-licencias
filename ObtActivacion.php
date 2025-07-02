<?
//*--------------------------------------------------------------------------*
// FUNCION:  ObtActivacion
// OBJETIVO: ALGORITMO QUE OBTIENE UN NUMERO EQUIVALENTE A PARTIR DE UN
//           CADENA PASADA
// PARAMETROS:pc_Serial C(n) CADENA, 
//            pc_Semilla n(1) SIEMPRE VA 1
//            pn_TipoLicencia n(2)
//              1 : Básica
//              2 : Demo
//              3 : Básica + Nómina
//              4 : Básica + Comprob.
//              5 : Básica + Nómina + Comprob.
//              6 : Básica + Fijos
//              7 : Básica + Fijos + Nómina
//              8 : Básica + Fijos + Comprob
//              9 : Básica + Fijos + Nómina + Comprob
//             10 : Básica + Prod
//             11 : Básica + Prod + Nómina
//             12 : Básica + Prod + Comprob.
//             13 : Básica + Prod + Nómina + Comprob.
//             14 : Básica + Prod + Fijos
//             15 : Básica + Prod + Fijos + Nómina
//             16 : Básica + Prod + Fijos + Comprob
//             17 : Básica + Prod + Fijos + Nómina + Comprob
// RETORNA:   EL NUMERO UNICO EQUIVALENTE DE LA MISMA LONGITUD
//*--------------------------------------------------------------------------*
function ObtActivacion($pc_Serial, $pn_Semilla = 1, $pn_TipoLicencia = 1) {
if (empty($pn_Semilla))
   $pn_Semilla = 0;

$lc_resultado = '';
$ln_j = 0;
for ($ln_i = 1; $ln_i <= strlen($pc_Serial); $ln_i++) {
   if ($ln_i > 10)
      $ln_j = 0;
   $ln_j++;
   $lc_digito = substr($pc_Serial, $ln_i-1, 1);
   if ($lc_digito == '-')
      if ($ln_i > 2)
          $lc_digito = substr($pc_Serial, 1, 1);
   $lc_digito = substr((string)(ord($lc_digito)),-1,1);
   $ln_digito = ((integer)$lc_digito);
   switch ($pn_TipoLicencia) {
   case 0:
   case 1:
	switch ($ln_j) {
	   case 1:
	     $ln_fac = 4;
	     break;
	   case 2:
	     $ln_fac = 5;
	     break;
	   case 3:
	     $ln_fac = 7;
	     break;
	   case 4:
	     $ln_fac = 3;
	     break;
	   case 5:
	     $ln_fac = 8;
	     break;
	   case 6:
	     $ln_fac = 4;
	     break;
	   case 7:
	     $ln_fac = 5;
	     break;
	   case 8:
	     $ln_fac = 5;
	     break;
	   case 9:
	     $ln_fac = 7;
	     break;
	   case 10:
	     $ln_fac = 1;
	     break;
	   }
        break;
   case 2:
	switch ($ln_j) {
	   case 1:
	     $ln_fac = 3;
	     break;
	   case 2:
	     $ln_fac = 8;
	     break;
	   case 3:
	     $ln_fac = 9;
	     break;
	   case 4:
	     $ln_fac = 2;
	     break;
	   case 5:
	     $ln_fac = 8;
	     break;
	   case 6:
	     $ln_fac = 6;
	     break;
	   case 7:
	     $ln_fac = 9;
	     break;
	   case 8:
	     $ln_fac = 5;
	     break;
	   case 9:
	     $ln_fac = 9;
	     break;
	   case 10:
	     $ln_fac = 2;
	     break;
	   }
       break;
   case 3:
       switch ($ln_j) {
	   case 1:
	     $ln_fac = 4;
	     break;
	   case 2:
	     $ln_fac = 1;
	     break;
	   case 3:
	     $ln_fac = 9;
	     break;
	   case 4:
	     $ln_fac = 3;
	     break;
	   case 5:
	     $ln_fac = 2;
	     break;
	   case 6:
	     $ln_fac = 7;
	     break;
	   case 7:
	     $ln_fac = 5;
	     break;
	   case 8:
	     $ln_fac = 8;
	     break;
	   case 9:
	     $ln_fac = 1;
	     break;
	   case 10:
	     $ln_fac = 6;
	     break;
 	   }
 	break;
   case 4:
       switch ($ln_j) {
	   case 1:
	     $ln_fac = 7;
	     break;
	   case 2:
	     $ln_fac = 2;
	     break;
	   case 3:
	     $ln_fac = 1;
	     break;
	   case 4:
	     $ln_fac = 3;
	     break;
	   case 5:
	     $ln_fac = 6;
	     break;
	   case 6:
	     $ln_fac = 5;
	     break;
	   case 7:
	     $ln_fac = 4;
	     break;
	   case 8:
	     $ln_fac = 8;
	     break;
	   case 9:
	     $ln_fac = 9;
	     break;
	   case 10:
	     $ln_fac = 1;
	     break;
 	   }
       break;
   case 5:
       switch ($ln_j) {
	   case 1:
	     $ln_fac = 1;
	     break;
	   case 2:
	     $ln_fac = 5;
	     break;
	   case 3:
	     $ln_fac = 2;
	     break;
	   case 4:
	     $ln_fac = 8;
	     break;
	   case 5:
	     $ln_fac = 9;
	     break;
	   case 6:
	     $ln_fac = 6;
	     break;
	   case 7:
	     $ln_fac = 3;
	     break;
	   case 8:
	     $ln_fac = 4;
	     break;
	   case 9:
	     $ln_fac = 7;
	     break;
	   case 10:
	     $ln_fac = 3;
	     break;
 	   }
       break;
   case 6:
       switch ($ln_j) {
	   case 1:
	     $ln_fac = 3;
	     break;
	   case 2:
	     $ln_fac = 1;
	     break;
	   case 3:
	     $ln_fac = 4;
	     break;
	   case 4:
	     $ln_fac = 2;
	     break;
	   case 5:
	     $ln_fac = 6;
	     break;
	   case 6:
	     $ln_fac = 5;
	     break;
	   case 7:
	     $ln_fac = 7;
	     break;
	   case 8:
	     $ln_fac = 9;
	     break;
	   case 9:
	     $ln_fac = 8;
	     break;
	   case 10:
	     $ln_fac = 4;
	     break;
 	   }
       break;

   case 7:
       switch ($ln_j) {
	   case 1:
	     $ln_fac = 4;
	     break;
	   case 2:
	     $ln_fac = 1;
	     break;
	   case 3:
	     $ln_fac = 3;
	     break;
	   case 4:
	     $ln_fac = 6;
	     break;
	   case 5:
	     $ln_fac = 8;
	     break;
	   case 6:
	     $ln_fac = 9;
	     break;
	   case 7:
	     $ln_fac = 2;
	     break;
	   case 8:
	     $ln_fac = 5;
	     break;
	   case 9:
	     $ln_fac = 7;
	     break;
	   case 10:
	     $ln_fac = 1;
	     break;
 	   }
       break;
   case 8:
       switch ($ln_j) {
	   case 1:
	     $ln_fac = 7;
	     break;
	   case 2:
	     $ln_fac = 3;
	     break;
	   case 3:
	     $ln_fac = 1;
	     break;
	   case 4:
	     $ln_fac = 4;
	     break;
	   case 5:
	     $ln_fac = 2;
	     break;
	   case 6:
	     $ln_fac = 5;
	     break;
	   case 7:
	     $ln_fac = 6;
	     break;
	   case 8:
	     $ln_fac = 9;
	     break;
	   case 9:
	     $ln_fac = 8;
	     break;
	   case 10:
	     $ln_fac = 5;
	     break;
 	   }
       break;
   
   case 9:
       switch ($ln_j) {
	   case 1:
	     $ln_fac = 8;
	     break;
	   case 2:
	     $ln_fac = 2;
	     break;
	   case 3:
	     $ln_fac = 1;
	     break;
	   case 4:
	     $ln_fac = 5;
	     break;
	   case 5:
	     $ln_fac = 6;
	     break;
	   case 6:
	     $ln_fac = 4;
	     break;
	   case 7:
	     $ln_fac = 3;
	     break;
	   case 8:
	     $ln_fac = 7;
	     break;
	   case 9:
	     $ln_fac = 9;
	     break;
	   case 10:
	     $ln_fac = 8;
	     break;
 	   }
       break;
       
   case 10:
	switch ($ln_j) {
	   case 1:
	     $ln_fac = 3;
	     break;
	   case 2:
	     $ln_fac = 2;
	     break;
	   case 3:
	     $ln_fac = 8;
	     break;
	   case 4:
	     $ln_fac = 8;
	     break;
	   case 5:
	     $ln_fac = 7;
	     break;
	   case 6:
	     $ln_fac = 9;
	     break;
	   case 7:
	     $ln_fac = 6;
	     break;
	   case 8:
	     $ln_fac = 5;
	     break;
	   case 9:
	     $ln_fac = 4;
	     break;
	   case 10:
	     $ln_fac = 1;
	     break;
	   }
        break;
   case 11:
	switch ($ln_j) {
	   case 1:
	     $ln_fac = 7;
	     break;
	   case 2:
	     $ln_fac = 4;
	     break;
	   case 3:
	     $ln_fac = 4;
	     break;
	   case 4:
	     $ln_fac = 5;
	     break;
	   case 5:
	     $ln_fac = 1;
	     break;
	   case 6:
	     $ln_fac = 2;
	     break;
	   case 7:
	     $ln_fac = 8;
	     break;
	   case 8:
	     $ln_fac = 6;
	     break;
	   case 9:
	     $ln_fac = 3;
	     break;
	   case 10:
	     $ln_fac = 9;
	     break;
	   }
       break;
   case 12:
       switch ($ln_j) {
	   case 1:
	     $ln_fac = 9;
	     break;
	   case 2:
	     $ln_fac = 5;
	     break;
	   case 3:
	     $ln_fac = 2;
	     break;
	   case 4:
	     $ln_fac = 3;
	     break;
	   case 5:
	     $ln_fac = 6;
	     break;
	   case 6:
	     $ln_fac = 4;
	     break;
	   case 7:
	     $ln_fac = 1;
	     break;
	   case 8:
	     $ln_fac = 2;
	     break;
	   case 9:
	     $ln_fac = 8;
	     break;
	   case 10:
	     $ln_fac = 7;
	     break;
 	   }
 	break;
   case 13:
       switch ($ln_j) {
	   case 1:
	     $ln_fac = 2;
	     break;
	   case 2:
	     $ln_fac = 5;
	     break;
	   case 3:
	     $ln_fac = 3;
	     break;
	   case 4:
	     $ln_fac = 1;
	     break;
	   case 5:
	     $ln_fac = 4;
	     break;
	   case 6:
	     $ln_fac = 6;
	     break;
	   case 7:
	     $ln_fac = 7;
	     break;
	   case 8:
	     $ln_fac = 8;
	     break;
	   case 9:
	     $ln_fac = 9;
	     break;
	   case 10:
	     $ln_fac = 6;
	     break;
 	   }
       break;
   case 14:
       switch ($ln_j) {
	   case 1:
	     $ln_fac = 3;
	     break;
	   case 2:
	     $ln_fac = 2;
	     break;
	   case 3:
	     $ln_fac = 6;
	     break;
	   case 4:
	     $ln_fac = 5;
	     break;
	   case 5:
	     $ln_fac = 9;
	     break;
	   case 6:
	     $ln_fac = 1;
	     break;
	   case 7:
	     $ln_fac = 4;
	     break;
	   case 8:
	     $ln_fac = 9;
	     break;
	   case 9:
	     $ln_fac = 8;
	     break;
	   case 10:
	     $ln_fac = 7;
	     break;
 	   }
       break;
   case 15:
       switch ($ln_j) {
	   case 1:
	     $ln_fac = 1;
	     break;
	   case 2:
	     $ln_fac = 9;
	     break;
	   case 3:
	     $ln_fac = 3;
	     break;
	   case 4:
	     $ln_fac = 2;
	     break;
	   case 5:
	     $ln_fac = 5;
	     break;
	   case 6:
	     $ln_fac = 4;
	     break;
	   case 7:
	     $ln_fac = 8;
	     break;
	   case 8:
	     $ln_fac = 7;
	     break;
	   case 9:
	     $ln_fac = 6;
	     break;
	   case 10:
	     $ln_fac = 1;
	     break;
 	   }
       break;

   case 16:
       switch ($ln_j) {
	   case 1:
	     $ln_fac = 2;
	     break;
	   case 2:
	     $ln_fac = 1;
	     break;
	   case 3:
	     $ln_fac = 3;
	     break;
	   case 4:
	     $ln_fac = 2;
	     break;
	   case 5:
	     $ln_fac = 6;
	     break;
	   case 6:
	     $ln_fac = 9;
	     break;
	   case 7:
	     $ln_fac = 8;
	     break;
	   case 8:
	     $ln_fac = 5;
	     break;
	   case 9:
	     $ln_fac = 4;
	     break;
	   case 10:
	     $ln_fac = 7;
	     break;
 	   }
       break;
   case 17:
       switch ($ln_j) {
	   case 1:
	     $ln_fac = 8;
	     break;
	   case 2:
	     $ln_fac = 5;
	     break;
	   case 3:
	     $ln_fac = 9;
	     break;
	   case 4:
	     $ln_fac = 7;
	     break;
	   case 5:
	     $ln_fac = 4;
	     break;
	   case 6:
	     $ln_fac = 6;
	     break;
	   case 7:
	     $ln_fac = 1;
	     break;
	   case 8:
	     $ln_fac = 2;
	     break;
	   case 9:
	     $ln_fac = 3;
	     break;
	   case 10:
	     $ln_fac = 8;
	     break;
 	   }
       break;
   
   case 18:
       switch ($ln_j) {
	   case 1:
	     $ln_fac = 5;
	     break;
	   case 2:
	     $ln_fac = 6;
	     break;
	   case 3:
	     $ln_fac = 4;
	     break;
	   case 4:
	     $ln_fac = 9;
	     break;
	   case 5:
	     $ln_fac = 8;
	     break;
	   case 6:
	     $ln_fac = 7;
	     break;
	   case 7:
	     $ln_fac = 2;
	     break;
	   case 8:
	     $ln_fac = 8;
	     break;
	   case 9:
	     $ln_fac = 1;
	     break;
	   case 10:
	     $ln_fac = 3;
	     break;
 	   }
       break;
   }

   $ln_fac = $ln_fac + $pn_Semilla;
   $lc_resultado = $lc_resultado . substr(trim((string)(($ln_digito + 1) * $ln_fac)),-1,1);
}
return $lc_resultado;
}
?>