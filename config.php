<?php
  $GLOBALS['doq']['env'] = [
    '#templatesPath'=>'templates',
    '#parsedTemplatesCachePath'=>'cache/templates',
    '@modules'=>[
      'auth'    =>['actions'=>['default'=>'auth_status.php']],
      'products'=>['actions'=>['default'=>'products_list.php']]
      ],
    '@caches'=>[
      'mysql1_dataplans'=>['#type'=>'serialfile','#cacheFolderPath'=>'cache/dataplans','#filePrefix'=>'vp_','#fileSuffix'=>'.plan.txt','#forceCreateFolder'=>1]
      ],
    '@session'=>[
      '#formNoncesSalt'=>'gJUYGo87fsghgO*sdfsGftu',
      '#sessionDataConnection'=>'MYSQL0', // база данных, в которых есть sys_signups(запросы на регистрацию), sys_users(зарегистрированные), sys_sessions (сессии), sys_client(браузеры), sys_form_nonces(токены для форм авторизации), 
      '#autoApprove'=>'1' // автоматически переносить в список допущенных пользователей
      ],
    '@dataConnections'=>[
      'MYSQL0'=>['#provider'=>'mysql','@params'=>[
        'host'=>'h906166802.mysql',
        'port'=>'3306',
        'dbase'=>'h906166802_db',
        'login'=>'h906166802_mysql',
        'password'=>'MA5NV/kD'],'#dataPlanCachePath'=>'cache/dataplans'],
        /*
      'MYSQL1'=>['#provider'=>'mysql','@params'=>
         ['host'=>'95.191.130.173',
         'port'=>'8036','dbase'=>'test','login'=>'tester','password'=>'lua2Gee'],'#dataPlanCachePath'=>'cache/dataplans'],
         */
      'localmem'=>['#provider'=>'memory']
    ],
  ];

  # Dataset schemas
  $GLOBALS['doq']['model'] = [
    '@datasources'=>[
      'main'=>[
        '#dataConnection'=>'MYSQL0',
        '@schemas'=>[
          'store'=>[
            '@datasets'=>[
              # dataset in the schema 'mystore' uses in model 'main' (type = 'memory')
              'PRODUCT_GROUPS'=>[
                '#kind'=>'tree', # = list for dictionaries, = tree for small navigation trees, = table for tables printing via DataGrid
                '#label'=>'Das ist Product groups',
                '@fields'=>[
                  'PRODUCT_GROUP_ID'=>['#type'=>'int64','#isAutoInc'=>'1'],
                  'PARENT_ID'=>       ['#type'=>'int64','#kind'=>'lookup','#ref'=>'store/PRODUCT_GROUPS'],
                  'NAME'=>            ['#type'=>'string','#size'=>'80'],
                  'SUB_NAME'=>        ['#type'=>'string','#size'=>'80'],
                  'TITLE'=>           ['#type'=>'string','#size'=>'180']
                ],
                '#keyField'=>'PRODUCT_GROUP_ID',
                '#nesting'=>['#rootId'=>0,'#parentIdField'=>'PARENT_ID']
              ],
              'PRODUCTS'=>[
                '#kind'=>'table',
                '@fields'=>[
                  'PRODUCT_ID'      =>[
                    '#type'=>'int64',
                    '#isAutoInc'=>1
                    ],
                  'PRODUCT_GROUP_ID'=>[
                    '#type'=>'int64',
                    '#kind'=>'lookup',
                    '#ref'=>'main:store/PRODUCT_GROUPS'], # достаточно лишь описать какой датасет используется
                  'PRODUCT_SECOND_GROUP_ID'=>[
                    '#type'=>'int64',
                    '#kind'=>'lookup',
                    '#ref'=>'main:store/PRODUCT_GROUPS'], # достаточно лишь описать какой датасет используется
                  'PRODUCT_TYPE_ID1' =>[
                    '#type'=>'int64',
                    '#kind'=>'lookup',
                    '#ref'=>'memdata:store/PRODUCT_TYPES',
                    ],
                  'PRODUCT_TYPE_ID2' =>[
                    '#type'=>'int64',
                    '#kind'=>'lookup',
                    '#ref'=>'memdata:store/PRODUCT_TYPES',
                    ],
                  'PARAMETERS'=>[
                    '#type'=>'virtual',
                    '#kind'=>'aggregation',
                    '#ref'=>'memdata:store/PRODUCT_PARAMETERS'
                    ],
                  'TITLE'=>['#type'=>'string','#size'=>80],
                  'SKU'=>['#type'=>'string','#size'=>30],

                ],
                '#keyField'=>'PRODUCT_ID'
              ],
            ],
          ]
        ],
      ],

      'memdata'=>[
      	'#dataConnection'=>'MYSQL0',
        '@schemas'=>[
          'store'=>[
            '@datasets'=>[
              'PRODUCT_TYPES'=>[
                '#kind'=>'list',
                '@fields'=>[
                  'PRODUCT_TYPE_ID'=>[
                    '#type'=>'int64',
                    '#isAutoInc'=>1,
                  ],
                  'NAME'=>[
                    '#type'=>'string',
                    '#size'=>50
                  ]
                ],
              '#keyField'=>'PRODUCT_TYPE_ID'
              ],
              'PARAMETERS'=>[
                '@fields'=>[
                  'PARAMETER_ID'=>['#type'=>'int64'],
                  'PARAMETER_GROUP_ID'=>['#type'=>'int64'],
                  'NAME'=>['#type'=>'string','#size'=>'100'],
                  'UNITS'>['#type'=>'string','#size'=>'50']
                ],
                '#keyField'=>'PARAMETER_ID'
              ],
              'PRODUCT_PARAMETERS'=>[
                '@fields'=>[
                  'PRODUCT_PARAMETER_ID'=>['#type'=>'int64'],
                  'PARAMETER_ID'=>['#type'=>'int64'],
                  'VALUE_STRING'=>['#type'=>'string','#size'=>'250'],
                  'PRODUCT_ID'=>[
                    '#type'=>'int64',
                    '#kind'=>'lookup',
                    '#ref'=>'main:store/PRODUCTS'
                  ],
                ],
                '#keyField'=>'PRODUCT_PARAMETER_ID'
              ]
            ]
          ]
        ]
      ]
    ]
  ];

$GLOBALS['doq']['views']=[
  'Products'=>[
    '#dataset'=>'main:store/PRODUCTS',
    'PRODUCT_ID'=>['#label'=>'Product Id'],
    'SKU'=>['#label'=>'SKU code'],
    'TITLE'=>['#label'=>'Product title'],
    'PARAMETERS'=>[
      '#label'=>'Product parameters',
      '@linked'=>[
        'PRODUCT_PARAMETER_ID'=>[
          '#label'=>'ProdParameterID'
          ],
        'PRODUCT_ID'=>[
          '#label'=>'The owner PRODUCT'
          ],
        'PARAMETER_VALUE'=>[
          '#field'=>'VALUE_STRING',
          '#label'=>'Parameter value',
        ],
      ],
    ],
    'PRODUCTGROUP'=>[
      '#field'=>'PRODUCT_GROUP_ID',
      '@linked'=>[
        'THE_PRODUCT_GROUP_NAME'=>[
          '#field'=>'NAME',
          '#label'=>'Group name',
        ],
        'THE_PRODUCT_GROUP_TITLE'=>[
          '#field'=>'TITLE',
          '#label'=>'Group title',
        ],
        'LINKED_PARENT_GROUP'=>[
          '#field'=>'PARENT_ID',
          '@linked'=>[
            'THE_PARENT_GROUP_NAME'=>[
              '#field'=>'NAME',
              '#label'=>'Parent group name'
            ]
          ]
        ]
      ]
    ],

    'SECONDGROUP'=>[
      '#field'=>'PRODUCT_SECOND_GROUP_ID',
      '@linked'=>[
        'PRODUCT_SECOND_GROUP_NAME'=>[
          '#field'=>'NAME',
          '#label'=>'Second group name',
        ],
      ],
    ],

    'THE_PRODUCT_TYPE'=>[
      '#field'=>'PRODUCT_TYPE_ID1',
      '@linked'=>[
#        '#ref'=>'memdata:dictionaries/PRODUCT_TYPES',
        'TYPE_NAME'=>[
          '#field'=>'NAME',
          '#label'=>'Type of of product'
        ],
        'PRODUCT_TYPE_ID'=>['#label'=>'ProdTypeID'],
      ]
    ],

    '@orderBy'=>['SKU'],
    '@searchForms'=>[
      'default'=>[
        'params'=>[
          '#bySKU'=>['#field'=>'SKU','#type'=>'filterByString','#filterMode'=>'like','#askLabel'=>'Укажите артикул или его часть'],
          '#byProductGroup'=>['#field'=>'PRODUCT_GROUP_ID','#type'=>'filterByOneOfComboBox','#askLabel'=>'Укажите группу товаров']
        ]
      ]
    ]
  ]
];


?>