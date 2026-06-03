<?php
namespace Absensi\helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Helper untuk kalkulasi geolocation.
 */
class GeoHelper {

    /**
     * Menghitung jarak antara dua koordinat (meter) menggunakan formula Haversine.
     *
     * @param float $lat1  Latitude titik 1 (derajat)
     * @param float $lng1  Longitude titik 1
     * @param float $lat2  Latitude titik 2
     * @param float $lng2  Longitude titik 2
     * @return float Jarak dalam meter
     */
    public static function haversine( float $lat1, float $lng1, float $lat2, float $lng2 ): float {
        if ( $lat1 === $lat2 && $lng1 === $lng2 ) {
            return 0.0;
        }

        $earth_radius = 6371000; // meter

        $phi1    = deg2rad( $lat1 );
        $phi2    = deg2rad( $lat2 );
        $dphi    = deg2rad( $lat2 - $lat1 );
        $dlambda = deg2rad( $lng2 - $lng1 );

        $a = sin( $dphi / 2 ) ** 2
            + cos( $phi1 ) * cos( $phi2 ) * sin( $dlambda / 2 ) ** 2;

        $c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

        return $earth_radius * $c;
    }

    /**
     * Validasi bahwa koordinat berada dalam rentang valid.
     */
    public static function is_valid( float $lat, float $lng ): bool {
        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    }
}
