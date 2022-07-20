
const Index = () => import('./components/l-limitless-bs4/Index');
const Form = () => import('./components/l-limitless-bs4/Form');
const Show = () => import('./components/l-limitless-bs4/Show');
const SideBarLeft = () => import('./components/l-limitless-bs4/SideBarLeft');
const SideBarRight = () => import('./components/l-limitless-bs4/SideBarRight');

const routes = [

    {
        path: '/goods-issued',
        components: {
            default: Index,
            //'sidebar-left': ComponentSidebarLeft,
            //'sidebar-right': ComponentSidebarRight
        },
        meta: {
            title: 'Accounting :: Goods Issued Notes',
            metaTags: [
                {
                    name: 'description',
                    content: 'Goods Issued Notes'
                },
                {
                    property: 'og:description',
                    content: 'Goods Issued Notes'
                }
            ]
        }
    },
    {
        path: '/goods-issued/create',
        components: {
            default: Form,
            //'sidebar-left': ComponentSidebarLeft,
            //'sidebar-right': ComponentSidebarRight
        },
        meta: {
            title: 'Accounting :: Goods Issued Note :: Create',
            metaTags: [
                {
                    name: 'description',
                    content: 'Create Goods Issued Note'
                },
                {
                    property: 'og:description',
                    content: 'Create Goods Issued Note'
                }
            ]
        }
    },
    {
        path: '/goods-issued/:id',
        components: {
            default: Show,
            'sidebar-left': SideBarLeft,
            'sidebar-right': SideBarRight
        },
        meta: {
            title: 'Accounting :: Goods Issued Note',
            metaTags: [
                {
                    name: 'description',
                    content: 'Goods Issued Note'
                },
                {
                    property: 'og:description',
                    content: 'Goods Issued Note'
                }
            ]
        }
    },
    {
        path: '/goods-issued/:id/copy',
        components: {
            default: Form,
        },
        meta: {
            title: 'Accounting :: Goods Issued Note :: Copy',
            metaTags: [
                {
                    name: 'description',
                    content: 'Copy Goods Issued Note'
                },
                {
                    property: 'og:description',
                    content: 'Copy Goods Issued Note'
                }
            ]
        }
    },
    {
        path: '/goods-issued/:id/edit',
        components: {
            default: Form,
        },
        meta: {
            title: 'Accounting :: Goods Issued Note :: Edit',
            metaTags: [
                {
                    name: 'description',
                    content: 'Edit Goods Issued Note'
                },
                {
                    property: 'og:description',
                    content: 'Edit Goods Issued Note'
                }
            ]
        }
    }

]

export default routes
