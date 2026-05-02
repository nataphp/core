<?php
/**
 * NataPHP Framework
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @link          http://nataphp.com NataPHP Project
 * @since         NataPHP 1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\Form\Element;

use Nata\Form\Element\Select;

/**
 * Country select element.
 */
class Country extends Select {

/**
 * Set country code as value option.
 *
 * @var bool
 */
    protected $_countryCodeValue;

/**
 * Countries list.
 *
 * @var array
 */
    protected $_countries = array(
        "Afghanistan" => 'AF',
        "Aland Islands" => 'AX',
        "Albania" => 'AL',
        "Algeria" => 'DZ',
        "American Samoa" => 'AS',
        "Andorra" => 'AD',
        "Angola" => 'AO',
        "Anguilla" => 'AI',
        "Antarctica" => 'AQ',
        "Antigua and Barbuda" => 'AG',
        "Argentina" => 'AR',
        "Armenia" => 'AM',
        "Aruba" => 'AW',
        "Australia" => 'AU',
        "Austria" => 'AT',
        "Azerbaijan" => 'AZ',
        "Bahamas, The" => 'BS',
        "Bahrain" => 'BH',
        "Bangladesh" => 'BD',
        "Barbados" => 'BB',
        "Belarus" => 'BY',
        "Belgium" => 'BE',
        "Belize" => 'BZ',
        "Benin" => 'BJ',
        "Bermuda" => 'BM',
        "Bhutan" => 'BT',
        "Bolivia" => 'BO',
        "Bosnia and Herzegovina" => 'BA',
        "Botswana" => 'BW',
        "Bouvet Island" => 'BV',
        "Brazil" => 'BR',
        "British Indian Ocean Territory" => 'IO',
        "Brunei Darussalam" => 'BN',
        "Bulgaria" => 'BG',
        "Burkina Faso" => 'BF',
        "Burundi" => 'BI',
        "Cambodia" => 'KH',
        "Cameroon" => 'CM',
        "Canada" => 'CA',
        "Cape Verde" => 'CV',
        "Cayman Islands" => 'KY',
        "Central African Republic" => 'CF',
        "Chad" => 'TD',
        "Chile" => 'CL',
        "China" => 'CN',
        "Christmas Island" => 'CX',
        "Cocos (Keeling) Islands" => 'CC',
        "Colombia" => 'CO',
        "Comoros" => 'KM',
        "Congo" => 'CG',
        "Congo, The Democratic Republic Of The" => 'CD',
        "Cook Islands" => 'CK',
        "Costa Rica" => 'CR',
        "Cote D'ivoire" => 'CI',
        "Croatia" => 'HR',
        "Cyprus" => 'CY',
        "Czech Republic" => 'CZ',
        "Denmark" => 'DK',
        "Djibouti" => 'DJ',
        "Dominica" => 'DM',
        "Dominican Republic" => 'DO',
        "Ecuador" => 'EC',
        "Egypt" => 'EG',
        "El Salvador" => 'SV',
        "Equatorial Guinea" => 'GQ',
        "Eritrea" => 'ER',
        "Estonia" => 'EE',
        "Ethiopia" => 'ET',
        "Falkland Islands (Malvinas)" => 'FK',
        "Faroe Islands" => 'FO',
        "Fiji" => 'FJ',
        "Finland" => 'FI',
        "France" => 'FR',
        "French Guiana" => 'GF',
        "French Polynesia" => 'PF',
        "French Southern Territories" => 'TF',
        "Gabon" => 'GA',
        "Gambia, The" => 'GM',
        "Georgia" => 'GE',
        "Germany" => 'DE',
        "Ghana" => 'GH',
        "Gibraltar" => 'GI',
        "Greece" => 'GR',
        "Greenland" => 'GL',
        "Grenada" => 'GD',
        "Guadeloupe" => 'GP',
        "Guam" => 'GU',
        "Guatemala" => 'GT',
        "Guernsey" => 'GG',
        "Guinea" => 'GN',
        "Guinea-Bissau" => 'GW',
        "Guyana" => 'GY',
        "Haiti" => 'HT',
        "Heard Island and the McDonald Islands" => 'HM',
        "Holy See" => 'VA',
        "Honduras" => 'HN',
        "Hong Kong" => 'HK',
        "Hungary" => 'HU',
        "Iceland" => 'IS',
        "India" => 'IN',
        "Indonesia" => 'ID',
        "Iraq" => 'IQ',
        "Ireland" => 'IE',
        "Isle Of Man" => 'IM',
        "Israel" => 'IL',
        "Italy" => 'IT',
        "Jamaica" => 'JM',
        "Japan" => 'JP',
        "Jersey" => 'JE',
        "Jordan" => 'JO',
        "Kazakhstan" => 'KZ',
        "Kenya" => 'KE',
        "Kiribati" => 'KI',
        "Korea, Republic Of" => 'KR',
        "Kuwait" => 'KW',
        "Kyrgyzstan" => 'KG',
        "Lao People's Democratic Republic" => 'LA',
        "Latvia" => 'LV',
        "Lebanon" => 'LB',
        "Lesotho" => 'LS',
        "Liberia" => 'LR',
        "Libya" => 'LY',
        "Liechtenstein" => 'LI',
        "Lithuania" => 'LT',
        "Luxembourg" => 'LU',
        "Macao" => 'MO',
        "Macedonia, The Former Yugoslav Republic Of" => 'MK',
        "Madagascar" => 'MG',
        "Malawi" => 'MW',
        "Malaysia" => 'MY',
        "Maldives" => 'MV',
        "Mali" => 'ML',
        "Malta" => 'MT',
        "Marshall Islands" => 'MH',
        "Martinique" => 'MQ',
        "Mauritania" => 'MR',
        "Mauritius" => 'MU',
        "Mayotte" => 'YT',
        "Mexico" => 'MX',
        "Micronesia, Federated States Of" => 'FM',
        "Moldova, Republic Of" => 'MD',
        "Monaco" => 'MC',
        "Mongolia" => 'MN',
        "Montenegro" => 'ME',
        "Montserrat" => 'MS',
        "Morocco" => 'MA',
        "Mozambique" => 'MZ',
        "Myanmar" => 'MM',
        "Namibia" => 'NA',
        "Nauru" => 'NR',
        "Nepal" => 'NP',
        "Netherlands" => 'NL',
        "Netherlands Antilles" => 'AN',
        "New Caledonia" => 'NC',
        "New Zealand" => 'NZ',
        "Nicaragua" => 'NI',
        "Niger" => 'NE',
        "Nigeria" => 'NG',
        "Niue" => 'NU',
        "Norfolk Island" => 'NF',
        "Northern Mariana Islands" => 'MP',
        "Norway" => 'NO',
        "Oman" => 'OM',
        "Pakistan" => 'PK',
        "Palau" => 'PW',
        "Palestinian Territories" => 'PS',
        "Panama" => 'PA',
        "Papua New Guinea" => 'PG',
        "Paraguay" => 'PY',
        "Peru" => 'PE',
        "Philippines" => 'PH',
        "Pitcairn" => 'PN',
        "Poland" => 'PL',
        "Portugal" => 'PT',
        "Puerto Rico" => 'PR',
        "Qatar" => 'QA',
        "Reunion" => 'RE',
        "Romania" => 'RO',
        "Russian Federation" => 'RU',
        "Rwanda" => 'RW',
        "Saint Barthelemy" => 'BL',
        "Saint Helena" => 'SH',
        "Saint Kitts and Nevis" => 'KN',
        "Saint Lucia" => 'LC',
        "Saint Martin" => 'MF',
        "Saint Pierre and Miquelon" => 'PM',
        "Saint Vincent and The Grenadines" => 'VC',
        "Samoa" => 'WS',
        "San Marino" => 'SM',
        "Sao Tome and Principe" => 'ST',
        "Saudi Arabia" => 'SA',
        "Senegal" => 'SN',
        "Serbia" => 'RS',
        "Seychelles" => 'SC',
        "Sierra Leone" => 'SL',
        "Singapore" => 'SG',
        "Slovakia" => 'SK',
        "Slovenia" => 'SI',
        "Solomon Islands" => 'SB',
        "Somalia" => 'SO',
        "South Africa" => 'ZA',
        "South Georgia and the South Sandwich Islands" => 'GS',
        "Spain" => 'ES',
        "Sri Lanka" => 'LK',
        "Suriname" => 'SR',
        "Svalbard and Jan Mayen" => 'SJ',
        "Swaziland" => 'SZ',
        "Sweden" => 'SE',
        "Switzerland" => 'CH',
        "Taiwan" => 'TW',
        "Tajikistan" => 'TJ',
        "Tanzania, United Republic Of" => 'TZ',
        "Thailand" => 'TH',
        "Timor-leste" => 'TL',
        "Togo" => 'TG',
        "Tokelau" => 'TK',
        "Tonga" => 'TO',
        "Trinidad and Tobago" => 'TT',
        "Tunisia" => 'TN',
        "Turkey" => 'TR',
        "Turkmenistan" => 'TM',
        "Turks and Caicos Islands" => 'TC',
        "Tuvalu" => 'TV',
        "Uganda" => 'UG',
        "Ukraine" => 'UA',
        "United Arab Emirates" => 'AE',
        "United Kingdom" => 'GB',
        "United States" => 'US',
        "United States Minor Outlying Islands" => 'UM',
        "Uruguay" => 'UY',
        "Uzbekistan" => 'UZ',
        "Vanuatu" => 'VU',
        "Venezuela" => 'VE',
        "Vietnam" => 'VN',
        "Virgin Islands, British" => 'VG',
        "Virgin Islands, U.S." => 'VI',
        "Wallis and Futuna" => 'WF',
        "Western Sahara" => 'EH',
        "Yemen" => 'YE',
        "Zambia" => 'ZM',
        "Zimbabwe" => 'ZW'
    );


/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        $config += array(
            'countryCodeValue' => null
        );

        $options = ($config['countryCodeValue'] ? $this->_countries : array_keys($this->_countries));
        $this->options()->loadAll($options);

        if ($this->placeholder() === null) {
            $this->_placeholder = __(' -- Select Country -- ');
        }

        parent::initialize($config);
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 */
    public function beforeRender($event) {
        parent::beforeRender($event);
        $this->template('select');
    }

}
