<?php
/*
Plugin Name: Uprava platobnych moznosti
Plugin URI: https://example.com/kontrola-gramatiky-ai
Description: Tento plugin využíva umelú inteligenciu na kontrolu gramatiky vo WordPress.
Version: 1.0
Author: Igor Majan
Author URI: https://example.com/about-igor-majan
License: GPL2
*/

// Plugin vytvára nové podmienky pre jednotlivé prepravné spolocnosti




// Nastavenie dopravy zadarma pre konkrétnu dopravu:
//Kód zmení cenu dopravy na 0 (doprava zdarma), v prípade, že je suma objednávky vyššia ako 200 €. - Vhodné napr. pre Packetu alebo Balikobox.
add_filter( 'woocommerce_package_rates', 'nastavenie_dopravy_zdarma_pre_objednavku_nad', 10, 2 );

function nastavenie_dopravy_zdarma_pre_objednavku_nad( $rates, $package ) {
    $order_total = WC()->cart->subtotal; // Získa celkovú cenu

    
    if ( isset( $rates['flat_rate:11'] ) ) {  // Zadajte hodnotu "value" dopravy, ktorú nájdete v zdrojom kóde v pokladni.  Príklad: flat_rate:11
        // Ak je cena objednávky vyššia ako 200, nastavícenu prepravy na 0
        if ( $order_total > 200 ) {
            $rates['flat_rate:11']->cost = 0;  // Tu tiež treba prepísať value ("flat_rate:11") dopravy + Zadať 0, aby sa poplatok zmenil na 0
             $rates['flat_rate:11']->label = 'Slovenská pošta Express kuriér - zdarma';  // Tu tiež treba prepísať value ("flat_rate:11") dopravy + Zadať nová názov danej dopravy. PRíklad: Slovenská pošta - zadarma
        }
    }

    return $rates;
}


// Vypnutie konkrétnej dopravy pre prekročenie určitej váhy - Napr. v prípade Packety alebo Balikoboxu. K produktom je potrebné zadať váhu.
add_filter( 'woocommerce_package_rates', 'skryt_dopravu_predopravu_podla_hmotnosti_produktu', 10, 2 );

function skryt_dopravu_predopravu_podla_hmotnosti_produktu( $rates, $package ) {
    // Prejdeme všetky položky v košíku a zistíme, či nejaký produkt má hmotnosť väčšiu ako 25 kg
    foreach ( $package['contents'] as $item_id => $values ) {
        $product = $values['data'];
        $product_weight = $product->get_weight(); // Získa váhu produktu

        // Ak je váha produktu väčšia ako 30 kg, skryj dopravu
        if ( $product_weight > 30 ) { //Zadajte hodnotu váhy (namiesto hodnoty 30)
            foreach ( $rates as $rate_key => $rate ) {
                // Kontrola, či je to dopravná možnosť, ktorú chceme skryť
                if ( 'flat_rate:11' === $rate->method_id ) { // Tu tiež treba prepísať value ("flat_rate:11") dopravy
                    unset( $rates[$rate_key] ); //Vypne konkrétnu dopravu
                }
            }
            break; // Ukáž len jedno varovanie, ak je objednávka viacero kusov
        }
    }

    return $rates;
}

// Koniec obmedzenie dopravy podľa váhy




// Obmedzenie dopravy podľa rozmerov produktu. Vhodné pre Packetu, Balikobox a niektorých kuriérov.
add_filter( 'woocommerce_package_rates', 'skryt_dopravu_podla_rozmerov', 10, 2 );

function skryt_dopravu_podla_rozmerov( $rates, $package ) {
    // Prejdeme všetky položky v košíku a zistíme, či nejaká má rozmery väčšie ako 60x50x45 cm
    foreach ( $package['contents'] as $item_id => $values ) {
        $product = $values['data'];
        $product_dimensions = $product->get_dimensions( false ); // Získa rozmery produktu

        // Rozmery produktu v cm (šírka x výška x hĺbka)
        $product_width = wc_get_dimension( $product_dimensions['width'], 'cm' );
        $product_height = wc_get_dimension( $product_dimensions['height'], 'cm' );
        $product_length = wc_get_dimension( $product_dimensions['length'], 'cm' );

        // Ak sú rozmery produktu väčšie ako 60x50x45 cm, skryj dopravu
        if ( $product_width > 60 || $product_height > 50 || $product_length > 45 ) { // Tu treba zmeniť hodnoty rozmerov (širka, výška, dĺžka) v cm.
            foreach ( $rates as $rate_key => $rate ) {
            if ( 'napostusk' === $rate->method_id ) { // Tu tiež treba prepísať value ("flat_rate:11") dopravy.
                unset( $rates[$rate_key] ); //Vypne konkrétnu dopravu
            }
            }
          
        }
         
        
    }

    return $rates;
}










//Znižujúca sa cena dopravy podľa počtu kusov alebo váhy
//Pri jednom projekte som mal požiadavku na úpravu dopravy podľa počtu kusov a váhy + pre jednotlivé kategórie mali byť nastavené rôzne ceny. Boli nato využité triedy dopravy, ktoré zlučovali niekoľko kategórie, ktoré mali rovnaké podmienky.



add_filter( 'woocommerce_package_rates', 'klesajuca_cena_dopravy_podla_triedy_dopravy', 10, 2 );
function klesajuca_cena_dopravy_podla_triedy_dopravy( $rates, $packages ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

    $cart_count = WC()->cart->get_cart_contents_count();
    $cart_total =  WC()->cart->cart_contents_total;
    $doprava = 5; //základna cena dopravy
    $cena_za_dopravu = array(); 

    foreach($rates as $rate_key => $rate_values ) {
        $method_id = $rate_values->method_id; 
        $rate_id = $rate_values->id;
        $product_id = array();
        foreach( WC()->cart->get_cart() as $cart_item ){
        $product_id[] = $cart_item['product_id'];
        
       
        $produkt = wc_get_product($cart_item['product_id']);

        $cena = wc_get_price_including_tax( $cart_item['data'] );

        $trieda_dopravy = $cart_item['data']->get_shipping_class_id();
        
        
        
        //CENY ZA KUS pre triedu dopravy 178.
        //Podmienka: Ak je počet menší ako 3 doprava bude 30 €. Ak je počet >= 3 tak cena bude počet kusov * doprava (10€)
        if(( $method_id == 'flat_rate' ) AND (( $trieda_dopravy == '178' ) OR ( $trieda_dopravy == '--' ) ) ){  
        $pocet_za_kus += $cart_item['quantity'];    
           if( $pocet_za_kus  < 3 ) {           //v prípade potreby zmente hodnotu 3 = počet kusov
                $doprava = 30;                  //cena dopravy
                $prepocet_za_kus =  $doprava;
                $cena_za_dopravu[] = $doprava; 
           } elseif( $pocet_za_kus  >= 3 ) {    //v prípade potreby zmente hodnotu 3 = počet kusov
                $doprava = 10;                  //cena dopravy
                $prepocet_za_kus = $pocet_za_kus * $doprava;
                $cena_za_dopravu[] = $prepocet_za_kus; 
        }
       }
       //CENY ZA KUS - ROZNE
       //Podmienka: Výpočet: počet kusov * hodnota dopravy (15€) 
        if(( $method_id == 'flat_rate' ) AND (( $trieda_dopravy == '195' ) OR ( $trieda_dopravy == '--' ) ) ){  
        $pocet_za_kus_rozne += $cart_item['quantity'];    
                $doprava = 15;
                $prepocet_za_kus_rozne = $pocet_za_kus_rozne * $doprava;
                $cena_za_dopravu[] = $prepocet_za_kus_rozne; 

       }

       //CENY ZA KG pre triedu dopravy 178.
       //Podmienka: Ak je počet menší alebo rovný ako 5 tak cena za dopravu bude 10.20€.  Ak je počet väčši ako 5 a menší alebo rovný ako 15, tak cena dopravy bude 15 €...
       if(( $method_id == 'flat_rate' ) AND (( $trieda_dopravy == '179' ) OR ( $trieda_dopravy == '--' ) ) ){
       $pocet_za_kg = $cart_item['quantity'];   
       $vaha = $cart_item['data']->get_weight(); 
       $prepocet_vahy = $pocet_za_kg * $vaha;  //prepočet váhy s počtom kusov   
           if( $prepocet_vahy <= 5 ) {
                $doprava = 10.20; 
                $prepocet_dopravy_za_kg = $doprava;
                $cena_za_dopravu[] = $prepocet_dopravy_za_kg;  
           } elseif( ($prepocet_vahy > 5 ) AND ($prepocet_vahy <= 15 )) {
                $doprava = 15.00;
                $prepocet_dopravy_za_kg = $doprava;
                $cena_za_dopravu []= $prepocet_dopravy_za_kg; 
        }  elseif( ($prepocet_vahy > 15 ) AND ($prepocet_vahy <= 30 )) {
                $doprava = 24;
                $prepocet_dopravy_za_kg = $doprava;
                $cena_za_dopravu []= $prepocet_dopravy_za_kg; 
        } elseif( $prepocet_vahy > 50  ) {
                $doprava = 33;
                $prepocet_dopravy_za_kg = $doprava;
                $cena_za_dopravu []= $prepocet_dopravy_za_kg; 
        }
       }

       
       //LEN OSOBNY ODBER - Ak je trieda dopravy 181 tak umožní len osobný odber objednávky
       if(( $method_id == 'flat_rate' ) AND (( $trieda_dopravy == '181' ) OR ( $trieda_dopravy == '--' ) ) ){ 
                unset($rates['flat_rate']);
                unset($rates['flat_rate:1']);
                $rates['local_pickup:3']->label = 'Osobný odber';
       }
       
       }


       //LEN OSOBNY ODBER - Ak nie je trieda dopravy 181 tak priradí cenu dopravy podľa podmienky vyššie.
if(( $method_id == 'flat_rate' ) AND (( $trieda_dopravy != '181' ) ) ){ 
$najvysia = max($cena_za_dopravu); 
$rates[$rate_id]->label = 'Kuriér';   //Môžete zmeniť názov dopravy    
$rates[$rate_id]->cost = number_format( $najvysia, 2 );         
}
       
}
return $rates;    
} 

