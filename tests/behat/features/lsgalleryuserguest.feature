Feature: Lsgallery plugin standart features BDD
  Test base functionality of LiveStreet by unautorize user

    Scenario: Check album as guest user
      Then check is plugin active "lsgallery"
      Given I load fixtures for plugin "lsgallery"

    #Check albums in public list
      Then I am going to page "/gallery/albums"

      Then I should see in element "content" values:
        | value |
        | /gallery/album/1">album opened</a> |

      Then I should not see in element "content" values:
        | value |
        | /gallery/album/2">album personal</a> |
        | /gallery/album/3">album friend</a> |

      #check images in sidebar
      Then I should see in element "sidebar" values:
        | value |
        | /gallery/image/1"> |
        | /gallery/image/2"> |

    #Check user's albums on userpage
      Then I am going to page "/profile/user-golfer/"
      Then I should see in element "content" values:
        | value |
        | /gallery/album/1"> |

      Then I should not see in element "content" values:
        | value |
        | /gallery/album/2"> |
        | /gallery/album/3"> |


    #Check user's created albums
      Then I am going to page "/profile/user-golfer/created/albums/"

      Then I should see in element "content" values:
        | value |
        | /gallery/album/1">album opened</a> |

      Then I should not see in element "content" values:
        | value |
        | /gallery/album/2">album personal</a> |
        | /gallery/album/3">album friend</a> |

    #Check for protected albums permissions
      Then I am going to page "/gallery/album/2"
      Then I should see in element "content" values:
        | value |
        | <p>No access</p> |

    #Check for image
      Then I am going to page "/gallery/image/1"
      Then I should see in element "content" values:
        | value |
        | http://livestreet.test/uploads/images/lsgallery/test2 |
        | http://livestreet.test/uploads/images/lsgallery/test3 |

      Then I should see in element "stream-images" values:
        | value |
        | http://livestreet.test/uploads/images/lsgallery/test2 |
        | http://livestreet.test/uploads/images/lsgallery/test3 |

      Then I should see in element "block_album" values:
        | value |
        | http://livestreet.test/uploads/images/lsgallery/test2 |
        | test album opened description text |

    #Check images on 'new' page
      Then I am going to page "/gallery/photo/new/"

      Then I should see in element "content" values:
        | value |
        | /gallery/image/1"> |
        | /gallery/image/2"> |

      Then I should not see in element "content" values:
        | value |
        | /gallery/image/3"> |
        | /gallery/image/5"> |

      Then I should see in element "stream-images" values:
        | value |
        | http://livestreet.test/uploads/images/lsgallery/test2 |
        | http://livestreet.test/uploads/images/lsgallery/test3 |