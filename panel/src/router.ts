import type { Component } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';

import Dashboard from './views/Dashboard.vue';
import MediaList from './views/MediaList.vue';
import StaticPageEditor from './views/StaticPageEditor.vue';
import PageLayout from './views/PageLayout.vue';
import PageList from './views/PageList.vue';
import PageEditor from './views/PageEditor.vue';
import NotFound from './views/404.vue';

import {
  LayoutDashboard,
  FolderOpen,
  Info,
  BookOpen,
  CircleQuestionMark,
} from '@lucide/vue';

declare module 'vue-router' {
  interface RouteMeta {
    title?: string
    nav?: {
      icon: Component
      order: number
    }
  }
}

const router = createRouter({
  history: createWebHistory('/panel'),
  routes: [
    {
      path: '/',
      component: Dashboard,
      name: 'dashboard',
      meta: {
        title: 'Dashboard',
        nav: { icon: LayoutDashboard, order: 1 },
      },
    },
    {
      path: '/media',
      component: MediaList,
      name: 'media',
      meta: {
        title: 'Media',
        nav: { icon: FolderOpen, order: 2 },
      },
    },
    {
      path: '/blog',
      component: PageLayout,
      name: 'blog',
      meta: {
        title: 'Blog',
        nav: { icon: BookOpen, order: 3 },
      },
      children: [
        {
          path: '',
          component: PageList,
          name: 'blog-list',
          meta: {
            title: 'Blog',
          },
          props: {
            configRoutePath: '/blog',
          },
        },
        {
          path: 'new',
          component: PageEditor,
          name: 'blog-create',
          meta: {
            title: 'New Page',
          },
          props: {
            mode: 'create',
            configRoutePath: '/blog',
          },
        },
        {
          path: ':slug',
          component: PageEditor,
          name: 'blog-edit',
          meta: {
            title: 'Edit Page',
          },
          props: (route) => ({
            mode: 'edit',
            slug: route.params.slug as string,
            configRoutePath: '/blog',
          }),
        },
      ],
    },
    {
      path: '/about',
      component: StaticPageEditor,
      name: 'about',
      meta: {
        title: 'About',
        nav: { icon: Info, order: 4 },
      },
      props: {
        configRoutePath: '/about',
        configContentPath: '/site/about/about.md',
      },
    },
    {
      path: '/faq',
      component: StaticPageEditor,
      name: 'faq',
      meta: {
        title: 'FAQ',
        nav: { icon: CircleQuestionMark, order: 5 },
      },
      props: {
        configRoutePath: '/faq',
        configContentPath: '/site/faq/faq.md',
      },
    },
    {
      path: '/:pathMatch(.*)*',
      component: NotFound,
      name: 'not-found',
      meta: { title: 'Page Not Found' },
    },
  ],
});

router.afterEach((to) => {
  const title = to.meta.title as string | undefined;
  document.title = title ? `${title} - ${window.APP.SITE_TITLE}` : window.APP.SITE_TITLE;
});

export default router;
